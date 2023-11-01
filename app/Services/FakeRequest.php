<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Chat;
use Carbon\Carbon;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FakeRequest
{
    // в dataset должен быть action и params = ['name' => value ...];
    public static function message(Chat $chat, string $text)
    {
        $payload = ['message' => []];

        $update_id = request()->input('update_id');

        $message_from = null;
        if(request()->has('message')) { // если сообщение
            $message = request()->input('message');
            $message_from = $message['from'];
        }

        if(request()->has('callback_query')) { // если нажатие на кнопку
            $callback_query = request()->input('callback_query');
            $message_from = $callback_query['from'];
        }

        /* проверяем тип чата взависимости в какой чат отправляем */
        $chat_type = null;
        if(Chat::where('chat_id', $chat->chat_id)->first()) {
            $chat_type = 'group';
        } else {
            $chat_type = 'private';
        }
        /* ----------------------------------------- */

        $message_chat = [
            'id' => $chat->chat_id,
            'type' => $chat_type
        ];

        $payload['update_id'] = $update_id;
        $payload['message']['message_id'] = 0;
        $payload['message']['from'] = $message_from;
        $payload['message']['chat'] = $message_chat;
        $payload['date'] = (Carbon::now())->timestamp;
        $payload['text'] = 'fake_request';
        return request()->replace($payload);
    }
    public static function callback_query(Chat|TelegraphChat $chat, Bot|TelegraphBot $bot, array $dataset)
    {
        $payload = [];

        $chat_type = null;
        if(Chat::where('chat_id', $chat->chat_id)->first()) {
            $chat_type = 'group';
        } else {
            $chat_type = 'private';
        }

        $message_chat = [
            'id' => $chat->chat_id,
            'type' => $chat_type
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

        Log::debug(json_encode($payload));

        return request()->replace($payload);
    }
}
