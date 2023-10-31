<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Admin;
use App\Http\Webhooks\Handlers\Courier;
use App\Http\Webhooks\Handlers\User;
use App\Http\Webhooks\Handlers\Washer;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\OrderStatusPivot;
use App\Http\Webhooks\Handlers\Manager;
use App\Models\Payment;
use App\Services\FakeRequest;
use App\Services\Helper;
use Carbon\Carbon;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatusPivot $orderStatus)
    {
        $order = $orderStatus->order;
        $update_order_dataset = null;
        $bot = null;

        if($order->status_id !== 1) {
            $bot = Bot::where('username', 'rastan_telegraph_bot')->first();
            $update_order_dataset = [
                'action' => 'update_order_card',
                'params' => [
                    'update_order_card' => 1,
                    'order_id' => $order->id
                ]
            ];

            if($order->status_id !== 3) {
                $manager_chat = Chat::where('name', 'Manager')->first();
                $manager_chat_request = FakeRequest::callback_query($manager_chat, $bot, $update_order_dataset);
                (new Manager())->handle($manager_chat_request, $bot);
            }
        }

        if(
            $order->status_id === 3 OR
            $order->status_id === 9 OR
            $order->status_id === 10 OR
            $order->status_id === 12
        ) { // отправка карточки заказа в чат курьеров

            if($order->status_id === 12) { // создание записи в payments
                Payment::create(['order_id' => $order->id, 'status_id' => 1]);
            }

            $courier_chat = Chat::where('name', 'Courier')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $courier_chat_request = FakeRequest::callback_query($courier_chat, $bot, $update_order_dataset);
            (new Courier())->handle($courier_chat_request, $bot);
        }

        if($order->status_id === 5 OR $order->status_id === 13 OR $order->status_id === 14) { // отправка уведомления клиенту
            $user_config = config('buttons.user');
            $chat = Chat::factory()->make([
                'chat_id' => $order->user->chat_id,
                'name' => 'User',
                'telegraph_bot_id' => 1
            ]);

            if($order->status_id === 5) {
                $status = $order->statuses()->where('id', 5)->first();
                $picked_time = (new Carbon($status->pivot->created_at))->format('Y-m-d H:i');
                $user_chat_dataset = [
                    'order' => $order,
                    'picked_time' => $picked_time
                ];
                Helper::send_user_notification($order->user, 'order_pickuped', $user_chat_dataset);
            } else if($order->status_id === 13) {
                $fake_dataset = [
                    'action' => 'payment_page',
                    'params' => [
                        'order_id' => $order->id,
                    ]
                ];

                $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
                (new User($order->user))->handle($fake_request, $bot);
            } else if($order->status_id === 14) {
                /* ОТПРАВКА КЛИЕНТУ ПРОСЬБЫ ОЦЕНИТЬ ЗАКАЗ */
                $fake_dataset = [
                    'action' => 'request_rating',
                    'params' => [
                        'order_id' => $order->id,
                    ]
                ];

                $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
                (new User($order->user))->handle($fake_request, $bot);
            }
        }

        if($order->status_id === 6) { // отправка в прачку
            $washer_chat = Chat::where('name', 'Washer')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $washer_chat_request = FakeRequest::callback_query($washer_chat, $bot, $update_order_dataset);
            (new Washer())->handle($washer_chat_request, $bot);
        }

        /* ОТПРАВКА В АДМИН ЧАТ */
        if($order->status_id === 13) {
            /* Если оплата картами => нужно пдтвердить */
            if($order->payment->status_id === 2 AND $order->payment->method_id !== 1) {
                $admin_chat = Chat::where('name', 'Admin')->first();
                $fake_dataset = [
                    'action' => 'test',
                    'params' => []
                ];

                $admin_chat_request = FakeRequest::callback_query($admin_chat, $bot, $fake_dataset);
                (new Admin())->handle($admin_chat_request, $bot);
            } else if($order->payment->status_id === 3) { // оплата бонусами
                // отправка на OK
            }
        }

    }
}
