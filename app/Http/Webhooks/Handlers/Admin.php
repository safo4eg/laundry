<?php

namespace App\Http\Webhooks\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;

class Admin extends WebhookHandler
{
    public function start(): void
    {
        $this->chat->message('Админ чат')->send();
    }
}
