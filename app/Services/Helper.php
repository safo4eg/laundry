<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Order;
use App\Models\User;
use DefStudio\Telegraph\Keyboard\Keyboard;

class Helper
{
    public static function send_user_notification($user, string $template, array $dataset = null, Keyboard $keyboard = null): void
    {
        $language_code = $user->language_code;
        $chat_id = $user->chat_id;
        $template_data = [];

        $template = "bot.user.{$language_code}.notifications.{$template}";

        $chat = Chat::factory()->make([
            'chat_id' => $chat_id,
            'name' => 'User',
            'telegraph_bot_id' => 1
        ]);

        $dataset = isset($dataset)? $dataset: [];

        if (!$keyboard){
            $chat->message(view($template, $dataset))->send();
        } else {
            $chat->message(view($template, $template_data))->keyboard($keyboard)->send();
        }
    }
}
