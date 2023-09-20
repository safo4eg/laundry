<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\OrderStatus;
use App\Models\ChatOrder;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class OrderStatusObserver
{
    public function created(OrderStatus $orderStatus)
    {
        $order = $orderStatus->order;
        $chat = null;
        $message_id = null;

        if($order->status_id === 2) {
            // этап когда заявка полностью заполнена пользователем
            // отправка в MANAGER CHAT
            $chat = Chat::where('name', 'Manager')->first();
            $message_id = ($chat
                ->message((string) view('bot.manager.order_distribution', ['order' => $order]))
                ->keyboard(Keyboard::make()
                    ->row([
                        Button::make('Courier_1')
                            ->action('send_to_couriers')
                            ->param('choice', 1)
                            ->param('order_id', $order->id),
                        Button::make('Courier_2')
                            ->action('send_to_couriers')
                            ->param('choice', 2)
                            ->param('order_id', $order->id)
                    ]))
                ->send())
                ->telegraphMessageId();

        }
//        else if($order->status_id === 3) {
//            // этап когда заявка только отправилась в чат курьеров
//            // отправка в MANAGER CHAT для отслеживания и взаимодействия
//            $chat = Chat::where('name', 'Manager')->first();
//            $message_id = $chat->message((string) view('bot.manager.order_info', ['order' => $order]))
//                ->send()
//                ->telegraphMessageId();
//        }
//
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
