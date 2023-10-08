<?php

namespace App\Http\Webhooks\Handlers;
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

    public function accept_order(): void
    {
        $this->chat->message('заказ принят')->send();
    }
}
