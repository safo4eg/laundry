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

        if (!$keyboard){
            $response = $chat->edit($user->message_id)->message($template)->send();
        } else {
            $response = $chat->edit($user->message_id)->message($template)->keyboard($keyboard)->send();
        }

        return $response;
    }
}
