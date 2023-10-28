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

            $main_chat_order = ChatOrderPivot::where('telegraph_chat_id', $courier_chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();

            /* Если статус_ид = 13 и есть карточка => ожидает оплату наличными */
            if($order->status_id === 13 AND isset($main_chat_order)) {
                if(in_array($attributes['method_id'], [2, 3])) {
                    /* отправляем на обновление карточки заказа */
                    /* со статусом заказа = 13 без метода оплаты=1 не зайдет в отправку новой карточки */
                    (new Courier())->handle($fake_request, $bot);
                }
            } else if($order->status_id === 13 AND !isset($main_chat_order)) {
                if($attributes['method_id'] == 1) {
                    (new Courier())->handle($fake_request, $bot);
                }
            }
        }
    }
}
