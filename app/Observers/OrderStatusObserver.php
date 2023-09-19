<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatus $orderStatus)
    {
        $status = $orderStatus->status_id;
        switch ($status) {
            case 2:
                $chat = Chat::where('chat_id', -4070334477)->first();
                $chat->message('создалась заявка')->send();
                break;
        }
    }
}
