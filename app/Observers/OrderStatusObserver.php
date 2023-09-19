<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\OrderStatus;
use App\Models\ChatOrder;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatus $orderStatus)
    {
        $order = $orderStatus->order;
        Log::debug($order);
        if($order->status_id === 2) {
            // этап когда заявка полностью заполнена пользователем
            // отправка в ADMIN CHAT на распределение
            $chat = Chat::where('chat_id', -4070334477)->first();
            $message_id = ($chat
                ->message((string) view('bot.admin.order_distribution', ['order' => $order]))
                ->send())
                ->telegraphMessageId();

            if(isset($message_id)) {
                ChatOrder::create([
                    'telegraph_chat_id' => $chat->id,
                    'order_id' => $order->id,
                    'message_id' => $message_id
                ]);
            }

        }
    }
}
