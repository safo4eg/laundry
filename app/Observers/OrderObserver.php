<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\ChatOrder;
use App\Models\Order;
use App\Models\OrderStatusPivot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        OrderStatusPivot::create([
            'order_id' => $order->id,
            'status_id' => $order->status_id,
            'created_at' => Carbon::now()
        ]);
    }

    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        $attributes = $order->getDirty();

        if(isset($attributes['status_id'])) {
            OrderStatusPivot::create([
                'order_id' => $order->id,
                'status_id' => $order->status_id,
                'created_at' => Carbon::now()
            ]);
        }

        if(isset($attributes['wishes'])) {
            $chat_order_active = ChatOrder::where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();

            $chat_order_wishes = ChatOrder::where('order_id', $order->id)
                ->where('message_type_id', 2)
                ->first();

            $chat = $chat_order_active->chat;

            if(isset($chat_order_wishes)) {
                $chat->deleteMessage($chat_order_wishes->message_id)->send();
                $chat_order_wishes->delete();
            }

            $response = $chat
                ->message("Пожелания к заказу: {$order->wishes}")
                ->reply($chat_order_active->message_id)
                ->send();

            ChatOrder::create([
                'telegraph_chat_id' => $chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 2
            ]);
        }
    }

}
