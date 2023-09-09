<?php

namespace App\Http\Webhooks\Handlers;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Courier extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Какой-то текст о возможностях бота')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Начать')->action('order_step_1')->param('choice', 1)
            ]))->send();
    }
}
