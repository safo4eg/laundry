<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Admin;
use App\Http\Webhooks\Handlers\Courier;
use App\Http\Webhooks\Handlers\User;
use App\Http\Webhooks\Handlers\Washer;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\OrderStatusPivot;
use App\Http\Webhooks\Handlers\Manager;
use App\Models\Payment;
use App\Models\Referral;
use App\Services\FakeRequest;
use App\Services\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatusPivot $orderStatus)
    {
        $attributes = $orderStatus->getDirty();
        $order = $orderStatus->order;
        $update_order_dataset = null;
        $bot = null;

        if($attributes['status_id'] !== 1) {
            $bot = Bot::where('username', 'rastan_telegraph_bot')->first();
            $update_order_dataset = [
                'action' => 'update_order_card',
                'params' => [
                    'update_order_card' => 1,
                    'order_id' => $order->id
                ]
            ];

            if($attributes['status_id'] !== 3 AND ($attributes['status_id'] !== 4 AND $order->reason_id !== 5)) {
                $manager_chat = Chat::where('name', 'Manager')->first();
                $manager_chat_request = FakeRequest::callback_query($manager_chat, $bot, $update_order_dataset);
                (new Manager())->handle($manager_chat_request, $bot);
            }
        }

        if(
            $attributes['status_id'] === 3 OR
            $attributes['status_id'] === 9 OR
            $attributes['status_id'] === 10 OR
            $attributes['status_id'] === 12
        ) { // отправка карточки заказа в чат курьеров

            if($attributes['status_id'] === 12) { // создание записи в payments
                Payment::create(['order_id' => $order->id, 'status_id' => 1]);
            }

            $courier_chat = Chat::where('name', 'Courier')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $courier_chat_request = FakeRequest::callback_query($courier_chat, $bot, $update_order_dataset);
            (new Courier())->handle($courier_chat_request, $bot);
        }

        if($attributes['status_id'] === 5 OR $attributes['status_id'] === 13 OR $attributes['status_id'] === 14) { // отправка уведомления клиенту
            $chat = Chat::factory()->make([
                'chat_id' => $order->user->chat_id,
                'name' => 'User',
                'telegraph_bot_id' => 1
            ]);

            if($attributes['status_id'] === 5) {
                $order_status = OrderStatusPivot::where('order_id', $order->id)
                    ->where('status_id', 5)
                    ->first();
                $picked_time = (new Carbon($order_status->created_at))->format('Y-m-d H:i');
                $user_chat_dataset = [
                    'order' => $order,
                    'picked_time' => $picked_time
                ];
                Helper::send_user_notification($order->user, 'order_pickuped', $user_chat_dataset);
            } else if($attributes['status_id'] === 13) {
                $fake_dataset = [
                    'action' => 'payment_page',
                    'params' => [
                        'order_id' => $order->id,
                    ]
                ];

                $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
                (new User($order->user))->handle($fake_request, $bot);
            } else if($attributes['status_id'] === 14) {
                /* ОТПРАВКА КЛИЕНТУ ПРОСЬБЫ ОЦЕНИТЬ ЗАКАЗ */
                $fake_dataset = [
                    'action' => 'request_rating',
                    'params' => [
                        'order_id' => $order->id,
                    ]
                ];

                $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
                (new User($order->user))->handle($fake_request, $bot);

                /* Добавление бабок пригласителю(реф система) */
                $referral = Referral::where('invited_id', $order->user->id)
                    ->first();

                if(isset($referral)) { // если пригласитель существует
                    /* Обновляем баланс пригласителя */
                    $inviter = $referral->inviter;
                    $bonus = $order->price*0.1;
                    if(isset($order->bonuses)) $bonus = $bonus + $order->bonuses*0.1;
                    $balance = $inviter->balance;
                    if(!isset($balance)) $balance = $bonus;
                    else $balance = $balance + $bonus;
                    $referral->update(['bonuses' => $bonus]);
                    $inviter->update(['balance' => $balance]);

                    $inviter_chat = Chat::factory()->make([
                        'chat_id' => $inviter->chat_id,
                        'name' => 'User',
                        'telegraph_bot_id' => 1
                    ]);

                    $fake_dataset = [
                        'action' => 'addition_balance',
                        'params' => [
                            'bonus' => $bonus,
                        ]
                    ];

                    $fake_request = FakeRequest::callback_query($inviter_chat, $bot, $fake_dataset);
                    (new User($inviter))->handle($fake_request, $bot);
                }
            }
        }

        if($attributes['status_id'] === 6) { // отправка в прачку
            $washer_chat = Chat::where('name', 'Washer')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $washer_chat_request = FakeRequest::callback_query($washer_chat, $bot, $update_order_dataset);
            (new Washer())->handle($washer_chat_request, $bot);
        }

        /* ОТПРАВКА В АДМИН ЧАТ */
        if($attributes['status_id'] === 13 OR $order->status_id === 14) {
            $admin_chat = Chat::where('name', 'Admin')->first();
            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $admin_chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();

            $fake_dataset = [
                'action' => 'send_card',
                'params' => [
                    'order_id' => $order->id
                ]
            ];

            /* Если была выбрана оплата и после этого курьер доставил вещи */
            if($attributes['status_id'] === 13) {
                if(($order->payment->method_id === 2 OR $order->payment->method_id === 3) AND $order->payment->status_id === 2) {
                    $admin_chat_request = FakeRequest::callback_query($admin_chat, $bot, $fake_dataset);
                    (new Admin())->handle($admin_chat_request, $bot);
                } else if($order->payment->method_id === 4) { // если уже было оплачено бонусами
                    $order->update(['status_id' => 14]);
                }
            }

            if($attributes['status_id'] === 14) {
                if(!isset($chat_order)) {
                    $admin_chat_request = FakeRequest::callback_query($admin_chat, $bot, $fake_dataset);
                    (new Admin())->handle($admin_chat_request, $bot);
                }
            }
        }

    }
}
