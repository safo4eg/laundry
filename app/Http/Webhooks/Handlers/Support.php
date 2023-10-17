<?php


namespace App\Http\Webhooks\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;

class Support extends WebhookHandler
{
    public function __construct()
    {
        $this->config = config('buttons.support');
        $this->template_prefix = 'bot.support.';
        parent::__construct();
    }

    public function start(): void
    {
        $this->reply('Чат поддержки');
    }
}
