<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\ChatOrder;
use App\Models\Laundry;
use App\Models\Order;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;


class Manager extends WebhookHandler
{
    use ChatsHelperTrait;

    public function __construct()
    {
        $this->config = config('buttons.manager');
        $this->template_prefix = 'bot.manager.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void // ообязательный метод(должен вообще быть у родителя абстрактным)
    {
        switch ($order->status_id) {
            case 2:
                $this->distribute($order);
                break;
        }
    }

    public function get_current_order_card_keyboard(Order $order): Keyboard|null
    {
        $keyboard = null;

        if($order->status_id === 2) {
            $keyboard = Keyboard::make();
            $laundries = Laundry::all();
            foreach ($laundries as $laundry)
            {
                $keyboard
                    ->button($laundry->title)
                    ->action('distribute')
                    ->param('distribute', 1)
                    ->param('laundry_id', $laundry->id)
                    ->param('order_id', $order->id);
            }
        }

        return $keyboard;
    }

    public function distribute(Order $order = null): void
    {
        $flag = $this->data->get('distribute');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) {
            $laundry_id = $this->data->get('laundry_id');
            $order->update([
                'status_id' => 3,
                'laundry_id' => $laundry_id
            ]);

            $this->update_order_card($order, $this->get_current_order_card_keyboard($order));
        }

        if(!isset($flag)) {
            $template = $this->template_prefix.'order_info';
            $keyboard = $this->get_current_order_card_keyboard($order);
            $response = $this->chat
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 1
            ]);
        }
    }

    public function refresh(string $order_id): void
    {
        ChatOrder::create([
            'telegraph_chat_id' => $this->chat->id,
            'order_id' => null,
            'message_id' => $this->messageId,
            'message_type_id' => 4
        ]);

        if($order_id == '/refresh') {
            $this->update_all_orders_cards_command();
            $this->remove_other_messages();
        } else {
            $order = $this->check_order_existence_in_chat_message($order_id);
            if(isset($order)) {
                $this->update_order_card_through_command($order);
                $this->remove_other_messages();
            }
        }
    }

}
