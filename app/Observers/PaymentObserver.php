<?php

namespace App\Observers;

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

        if (isset($attributes['method_id'])) {
            if ($order->status_id === 13) {
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
            }
        }

        /* Если оплата не прошла проверку: */
        /* Отменил курьер или Администратор не подтвердил */
        if (!isset($attributes['method_id'])) {
            Log::debug('зашло сюда');
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
