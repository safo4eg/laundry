<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderStatus;
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
        OrderStatus::create(['order_id' => $order->id, 'status_id' => $order->status_id]);
    }

    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updating(Order $order)
    {
        $attributes = $order->getDirty();

        if(isset($attributes['status_id'])) {
            OrderStatus::create(['order_id' => $order->id, 'status_id' => $order->status_id]);
        }
    }

}
