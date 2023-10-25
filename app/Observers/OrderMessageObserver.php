<?php

namespace App\Observers;

use App\Models\OrderMessage;
use Carbon\Carbon;

class OrderMessageObserver
{
    /**
     * Handle the OrderMessage "created" event.
     *
     * @param  \App\Models\OrderMessage  $orderMessage
     * @return void
     */
    public function creating(OrderMessage $orderMessage)
    {
        $orderMessage->created_at = Carbon::now();
    }

}
