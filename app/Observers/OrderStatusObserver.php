<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\Laundry;
use App\Models\OrderStatusPivot;
use App\Models\ChatOrder;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatusPivot $orderStatus)
    {
        $order = $orderStatus->order;
        $chat = null;
        $message_id = null;

        if($order->status_id === 2) {
            // этап когда заявка полностью заполнена пользователем
            // отправка в MANAGER CHAT

            $laundries = Laundry::all();
            $keyboard = Keyboard::make();
            foreach ($laundries as $laundry)
            {
                $courier_chat = $laundry->chats()->where('name', $laundry->title.'Couriers')->first();
                $keyboard
                    ->button($laundry->title)
                    ->action('send_to_couriers')
                    ->param('courier_chat_id', $courier_chat->chat_id)
                    ->param('order_id', $order->id);
            }

            $chat = Chat::where('name', 'Manager')->first();
            $message_id = ($chat
                ->message((string) view('bot.manager.order_distribution', ['order' => $order]))
                ->keyboard($keyboard)
                ->send())
                ->telegraphMessageId();

        }

        if(isset($message_id)) {
            ChatOrder::create([
                'telegraph_chat_id' => $chat->id,
                'order_id' => $order->id,
                'message_id' => $message_id,
                'message_type_id' => 1
            ]);
        }

    }
}
