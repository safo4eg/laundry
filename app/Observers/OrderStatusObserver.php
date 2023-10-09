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
                $keyboard
                    ->button($laundry->title)
                    ->action('distribute')
                    ->param('distribute', 1)
                    ->param('laundry_id', $laundry->id)
                    ->param('order_id', $order->id);
            }

            $chat = Chat::where('name', 'Manager')->first();
            $message_id = ($chat
                ->message((string) view('bot.manager.order_info', ['order' => $order]))
                ->keyboard($keyboard)
                ->send())
                ->telegraphMessageId();

        } else if($order->status_id == 3) {
            $chat = Chat::where('name', 'Courier')
                ->where('laundry_id', $order->laundry_id)
                ->first();

            $button_texts = config('buttons.courier.pickup');
            $keyboard = Keyboard::make()->buttons([
                Button::make($button_texts['pickup'])
                    ->action('pickup')
                    ->param('pickup', 1)
                    ->param('order_id', $order->id)
            ]);

            $message_id = ($chat
                ->message((string) view('bot.courier.order_info', ['order' => $order]))
                ->keyboard($keyboard)
                ->send())
                ->telegraphMessageId();
        } else if($order->status_id == 5) {
            // отправка уведомления пользователю о том, что курьер принял заказ
            // редактирования карточки в чате менеджеров
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
