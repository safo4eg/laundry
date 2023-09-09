<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\InlinePageTrait;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Models\TelegraphChat;

use App\Models\User as UserModel;
use App\Http\Webhooks\Handlers\Traits\UserTrait;
use Illuminate\Support\Facades\Log;



class User extends WebhookHandler
{
    use InlinePageTrait;

    public function test(): void
    {
        $language_code = 'ru';
        $buttons = [
                'row' => [
                    [
                        'name' => 'Start',
                        'type' => 'action',
                        'method' => 'run_test',
                        'param' => ['data', 'test'],
                        'language' => ['ru' => 'Начать', 'en' => 'Start']
                    ],

                    [
                        'name' => 'Start',
                        'type' => 'url',
                        'link' => 'https://t.me/+zZMk776R0oA0YWEy',
                        'language' => ['ru' => 'Начать', 'en' => 'Start']
                    ]
                ]
        ];
        $this->send_inline_page($language_code, 'start', $buttons);
    }
    public function start(): void
    {
        $chat_id = $this->message->from()->id();
        $user = UserModel::where('chat_id', $chat_id)->first();

        if($user) {
            $this->send_start($user);
            return;
        }

        $username = $this->message->from()->username();
        $language_code = $this->message->from()->languageCode();

        UserModel::create([
            'chat_id' => $chat_id,
            'username' => $username,
            'language_code' => $language_code
        ]);

        $this->send_select_language();
    }

    public function select_language(): void
    {
        $language_code = $this->data->get('language_code');
        $chat_id = $this->callbackQuery->from()->id();
        $message_id = $this->callbackQuery->message()->id();
        $this->chat->edit($message_id)->message('new text')->keyboard(
            Keyboard::make()->buttons([
                Button::make('text')->action('act')->param('par', 1)
            ])
        )->send();
//        $user = UserModel::where('chat_id', $chat_id)->first();
//        $user->updateFields(['language_code' => $language_code]);
//        $this->send_start($user);
    }


}
