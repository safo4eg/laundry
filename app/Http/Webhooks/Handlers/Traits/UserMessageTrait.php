<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait UserMessageTrait
{
    public function send_message_to_user(int $chat_id, string $template, Keyboard $keyboard = null): void
    {
        $chat = Chat::factory()->make([
            'chat_id' => $chat_id,
            'name' => 'Temp',
            'telegraph_bot_id' => 1
        ]);

        if (!$keyboard){
            $chat->message($template)->send();
        } else {
            $chat->message($template)->keyboard($keyboard)->send();
        }
    }
}
