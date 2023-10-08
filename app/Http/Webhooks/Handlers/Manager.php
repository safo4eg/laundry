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
    public function send_to_couriers(): void
    {
        $order_id = $this->data->get('order_id');
        $laundry_id = $this->data->get('laundry_id');

        $order = Order::find($order_id);
        $order->update([
            'status_id' => 3,
            'laundry_id' => $laundry_id
        ]);

        $this->update_order_card($order, $this->get_current_order_card_keyboard($order));
        // обновляем инфу о карте
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
                $this->update_order_card_through_command($order, $this->get_current_order_card_keyboard($order));
                $this->remove_other_messages();
            }
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
                    ->action('send_to_couriers')
                    ->param('laundry_id', $laundry->id)
                    ->param('order_id', $order->id);
            }
        }

        return $keyboard;
    }
}
