<?php

namespace App\Observers;

use App\Models\Bot;
use App\Models\Chat;
use App\Models\Laundry;
use App\Models\OrderStatusPivot;
use App\Models\ChatOrder;
use App\Http\Webhooks\Handlers\Manager;
use App\Services\FakeRequest;
use App\Services\Helper;
use Carbon\Carbon;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use function Webmozart\Assert\Tests\StaticAnalysis\null;

class OrderStatusObserver
{
    public function created(OrderStatusPivot $orderStatus)
    {
        $order = $orderStatus->order;
        $chat = null;
        $message_id = null;
        $bot = Bot::where('username', 'rastan_telegraph_bot')->first();

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
            $manager_chat = Chat::where('name', 'Manager')->first();
            $manager_chat_dataset = [
                'action' => 'update_order_card',
                'params' => [
                    'update_order_card' => 1,
                    'order_id' => $order->id
                ]
            ];
            $request = FakeRequest::callback_query($manager_chat, $bot, $manager_chat_dataset);
            (new Manager())->handle($request, $bot);

            $status = $order->statuses()->where('name', 'picked')->first();
            $picked_time = (new Carbon($status->pivot->created_at))->format('Y-m-d H:i');
            $user_chat_dataset = [
                'order' => $order,
                'picked_time' => $picked_time
            ];

            Helper::send_user_notification($order->user, 'order_pickuped', $user_chat_dataset);
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
