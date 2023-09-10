<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\InlinePageTrait;
use DefStudio\Telegraph\Handlers\WebhookHandler;

use App\Models\User as UserModel;
use App\Http\Webhooks\Handlers\Traits\UserTrait;
use Illuminate\Support\Facades\Log;



class User extends WebhookHandler
{
    use InlinePageTrait;
    public function start(): void
    {
        $choice = $this->data->get('choice');

        if(empty($choice)) {
            $chat_id = $this->message->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();

            if($user) {
                $language_code = $user->language_code;
                $template_name = config('keyboards.start.template');
                $buttons = config('keyboards.start.buttons');
                $this->send_inline_page($language_code, $template_name, $buttons);
                return;
            } else {
                $username = $this->message->from()->username();
                $language_code = $this->message->from()->languageCode();

                UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                    'language_code' => $language_code
                ]);

                $this->send_select_language();
            }
        }

        if(!empty($choice)) {
            $chat_id = $this->callbackQuery->from()->id();
            $message_id = $this->callbackQuery->message()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();

            // переход на сценарий
        }

    }

    public function select_language(): void
    {
        $language_code = $this->data->get('choice');
        if(empty($language_code)) {
            // этот блок для отправки выбора языка из комманд (нижний нужно будет немного переписать, чтобы была отправка не на начать)
        }

        if(!empty($language_code)) {
            $chat_id = $this->callbackQuery->from()->id();
            $message_id = $this->callbackQuery->message()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();
            $user->updateFields(['language_code' => $language_code]);

            $template_name = config('keyboards.start.template');
            $buttons = config('keyboards.start.buttons');
            $this->next_inline_page($message_id, $language_code, $template_name, $buttons);
        }
    }


}
