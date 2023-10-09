<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use App\Models\ChatOrder;
use App\Models\Order;
use App\Models\OrderStatus;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function Webmozart\Assert\Tests\StaticAnalysis\null;

trait ChatsHelperTrait
{
    public function update_order_card_through_command(Order $order)
    {
        $chat_orders = ChatOrder::where('order_id', $order->id)
            ->where('telegraph_chat_id', $this->chat->id)
            ->get();

        foreach ($chat_orders as $chat_order) {
            if($chat_order->message_type_id === 1) {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
                $this->send_order_card($order);
            }
            else {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
            }
        }
    }
    public function update_order_card(Order $order, Keyboard $keyboard = null)
    {
        $chat_orders = ChatOrder::where('order_id', $order->id)
            ->where('telegraph_chat_id', $this->chat->id)
            ->get();

        $chat_order_main = $chat_orders
            ->where('message_type_id', 1)
            ->first();

        $template = $this->template_prefix.'order_info';

        foreach ($chat_orders as $chat_order) {
            if($chat_order->message_type_id != 1) {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
            }
        }

        if(isset($keyboard)) {
            $this->chat
                ->edit($chat_order_main->message_id)
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
        } else {
            $this->chat
                ->edit($chat_order_main->message_id)
                ->message(view($template, ['order' => $order]))
                ->send();
        }
    }

    public function update_all_orders_cards_command():void
    {
        $orders = Order::whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('chat_order')
                ->whereColumn('chat_order.order_id', 'orders.id')
                ->where('telegraph_chat_id', $this->chat->id);
        })
            ->get();

        if($orders->count() > 0) {
            foreach ($orders as $order) {
                $this->update_order_card_through_command($order);
            }
        }
    }

    public function check_order_existence_in_chat_message(string|int $order_id): Order|null
    {
        $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
            ->where('order_id', $order_id)
            ->first();

        if(isset($chat_order)) {
            return $chat_order->order;
        } else {
            $template = 'bot.notifications.order_is_null';
            $response = $this->chat
                ->message(view($template, ['order_id' => $order_id]))
                ->send();

            DB::table('chat_order')->insert([
                [
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => null,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 3
                ]
            ]);
            return null;
        }
    }

    public function remove_other_messages(): void
    {
        $other_messages = ChatOrder::where('telegraph_chat_id', $this->chat->id)
            ->where('order_id', null)
            ->get();
        if($other_messages->count() > 0) {
            foreach ($other_messages as $other_message) {
                $this->chat->deleteMessage($other_message->message_id)->send();
                $other_message->delete();
            }
        }
    }
}
