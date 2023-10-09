<?php

namespace App\Http\Webhooks\Handlers;
use App\Models\Order;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Courier extends WebhookHandler
{
    public function __construct()
    {
        $this->config = config('buttons.courier');
        $this->template_prefix = 'bot.courier.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void // ообязательный метод(должен вообще быть у родителя абстрактным)
    {
        switch ($order->status_id) {
            case 3:
                $this->pickup($order);
                break;
        }
    }

    public function get_current_order_card_keyboard(Order $order): Keyboard|null
    {
        $keyboard = null;

        if($order->status_id == 5) {
            $buttons_texts = $this->config['pickup'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['pickup'])
                    ->action('pickup')
                    ->param('pickup', 1)
                    ->param('order_id', $order->id)
            ]);
        }

        return $keyboard;
    }

    public function pickup(Order $order = null): void
    {
        $flag = $this->data->get('pickup');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) { // Обработка данных с кнопки
            $this->chat->message('обработка поднятия заказа')->send();
        }

        if(!isset($flag)) { // отображения карточки с кнопками
            $template = $this->template_prefix.'order_info';
            $keyboard = $this->get_current_order_card_keyboard($order);
            $this->chat
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
        }
    }
}
