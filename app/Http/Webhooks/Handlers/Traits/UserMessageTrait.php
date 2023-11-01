<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use App\Models\User;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait UserMessageTrait
{
    public function send_message_to_user(User $user, string $template, Keyboard $keyboard = null): \DefStudio\Telegraph\Client\TelegraphResponse
    {
        $chat = Chat::factory()->make([
            'chat_id' => $user->chat_id,
            'name' => 'Temp',
            'telegraph_bot_id' => 1
        ]);

        if (!$keyboard) {
            $response = $chat->message($template)->send();
        } else {
            $response = $chat->message($template)->keyboard($keyboard)->send();
        }

        return $response;
    }
}
