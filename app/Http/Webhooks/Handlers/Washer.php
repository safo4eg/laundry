<?php

namespace App\Http\Webhooks\Handlers;
use DefStudio\Telegraph\Handlers\WebhookHandler;

class Washer extends WebhookHandler
{
    public function start(): void
    {
        $this->reply('Чат стиралок');
    }
}
