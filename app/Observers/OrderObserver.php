<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\Order;
use App\Models\OrderStatus;
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
        OrderStatus::create([
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
            OrderStatus::create([
                'order_id' => $order->id,
                'status_id' => $order->status_id,
                'created_at' => Carbon::now()
            ]);
        }

        if(isset($attributes['wishes'])) {
            $chat = $order->chats->where('name', 'Manager')->first();
            $chat
                ->message("Пожелания к заказу: {$order->wishes}")
                ->reply($chat->pivot->message_id)
                ->send();
        }
    }

}
