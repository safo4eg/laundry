<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Chat;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FakeRequest
{
    // в dataset должен быть action и params = ['name' => value ...];
    public static function callback_query(Chat $chat, Bot $bot, array $dataset)
    {
        $payload = [];

        $message_chat = [
            'id' => $chat->chat_id,
            'type' => 'group'
        ];

        $message_from = [
            'id' => $bot->bot_id,
            'is_bot' => true,
            'first_name' => $bot->first_name,
            'username' => $bot->username
        ];

        $update_id = request()->input('update_id');
        $payload['update_id'] = $update_id;

        if(request()->has('message')) { // если сообщение
            $message = request()->input('message');
            $payload['callback_query']['from'] = $message['from'];
            $payload['callback_query']['message']['text'] = 'fake_callback';
        }

        if(request()->has('callback_query')) { // если нажатие на кнопку
            $callback_query = request()->input('callback_query');
            $payload['callback_query']['from'] = $callback_query['from'];
        }

        $payload['callback_query']['message']['date'] = (Carbon::now())->timestamp;
        $payload['callback_query']['message']['from'] = $message_from; // инфа о боте
        $payload['callback_query']['message']['chat'] = $message_chat; // инфа о чате
        $payload['callback_query']['message']['message_id'] = 0; // инфа о message
        $payload['callback_query']['id'] = 0; // инфа о callback_query

        $payload['callback_query']['data'] = "action:{$dataset['action']}";
        foreach ($dataset['params'] as $name => $value) {
            $payload['callback_query']['data'] = $payload['callback_query']['data'].";$name:$value";
        }

        return request()->replace($payload);
    }
}
