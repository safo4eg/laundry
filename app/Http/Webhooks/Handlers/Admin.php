<?php

namespace App\Http\Webhooks\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\Order;

class Admin extends WebhookHandler
{
    public function test(): void
    {
        $this->chat->message('карточка на подтверждение')->send();
    }
}
