<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Courier;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\Payment;
use App\Services\FakeRequest;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    public function updated(Payment $payment)
    {
        $bot = Bot::where('id', 1)->first();
        $order = $payment->order;
        $attributes = $payment->getDirty();

        if(isset($attributes['method_id'])) {
            /* Если курьер ожидает оплаты наличными */
            if($order->status_id === 13) {
                /* Если сменили на оплату картой */
                if(in_array($attributes['method_id'], [2, 3])) {
                    $order->update(['status_id' => 14]);

                    $courier_chat = Chat::where('name', 'Courier')
                        ->where('laundry_id', $order->laundry_id)
                        ->first();

                    $main_chat_order = ChatOrderPivot::where('telegraph_chat_id', $courier_chat->id)
                        ->where('order_id', $order->id)
                        ->where('message_type_id', 1)
                        ->first();

                    if(isset($main_chat_order)) {
                        /* отправляем на удаление карточки заказа */
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
            }
        }
    }
}
