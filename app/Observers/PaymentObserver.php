<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Admin;
use App\Http\Webhooks\Handlers\Courier;
use App\Http\Webhooks\Handlers\User;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\Payment;
use App\Services\FakeRequest;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    public function created(Payment $payment)
    {
        $bot = Bot::where('id', 1)->first();
        $order = $payment->order;
        $attributes = $payment->getDirty();

        if ($order->status_id === 12) {
            if (!isset($attributes['method_id'])) {
                $chat = Chat::factory()->make([
                    'chat_id' => $order->user->chat_id,
                    'name' => 'User',
                    'telegraph_bot_id' => 1
                ]);

                $fake_dataset = [
                    'action' => 'payment_page',
                    'params' => [
                        'order_id' => $order->id,
                    ]
                ];

                $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
                (new User($order->user))->handle($fake_request, $bot);
            }
        }

    }

    public function updated(Payment $payment)
    {
        $bot = Bot::where('id', 1)->first();
        $order = $payment->order;
        $attributes = $payment->getDirty();

        if (isset($attributes['status_id'])) {
            // заказ уже доставлен
            if ($order->status_id === 13) {
                /* смена статуса на ожидает подтверждения платежа */
                if ($attributes['status_id'] === 2) {
                    if ($order->payment->method_id === 1) {
                        $courier_chat = Chat::where('name', 'Courier')
                            ->where('laundry_id', $order->laundry_id)
                            ->first();

                        $fake_callback_dataset = [
                            'action' => 'update_order_card',
                            'params' => [
                                'update_order_card' => 1,
                                'order_id' => $order->id
                            ]
                        ];

                        $fake_request = FakeRequest::callback_query($courier_chat, $bot, $fake_callback_dataset);
                        (new Courier())->handle($fake_request, $bot);
                    } else if ($order->payment->method_id === 2 or $order->payment->method_id === 3) {
                        $admin_chat = Chat::where('name', 'Admin')->first();
                        $fake_dataset = [
                            'action' => 'send_card',
                            'params' => [
                                'order_id' => $order->id
                            ]
                        ];
                        $fake_request = FakeRequest::callback_query($admin_chat, $bot, $fake_dataset);
                        (new Admin())->handle($fake_request, $bot);
                    }
                } else if($attributes['status_id'] === 3) {
                    if($order->payment->method_id === 4) {
                        $order->update(['status_id' => 14]);
                    }
                }

            }
        }

        /* Если оплата не прошла проверку: */
        /* Отменил курьер или Администратор не подтвердил */
        if (array_key_exists('method_id', $attributes) and !isset($attributes['method_id'])) {
            $chat = Chat::factory()->make([
                'chat_id' => $order->user->chat_id,
                'name' => 'User',
                'telegraph_bot_id' => 1
            ]);

            $fake_dataset = [
                'action' => 'unpaid_orders',
                'params' => [
                    'fake' => 1
                ]
            ];

            $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
            (new User($order->user))->handle($fake_request, $bot);
        }
    }
}
