<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\InlinePageTrait;
use DefStudio\Telegraph\Handlers\WebhookHandler;

use App\Models\User as UserModel;
use App\Http\Webhooks\Handlers\Traits\UserTrait;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;


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
                if($user->page === 'menu') {
                    $language_code = $user->language_code;
                    $template_name = config('keyboards.start.template');
                    $buttons = config('keyboards.start.buttons');
                    $user->updateFields(['page' => 'start']);
                    $this->send_inline_page($language_code, $template_name, $buttons);
                    return;
                } else return;
            } else {
                $username = $this->message->from()->username();
                $language_code = $this->message->from()->languageCode();

                $user = UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                    'language_code' => $language_code,
                    'page' => 'select_language'
                ]);

                $this->send_select_language();
            }
        }

        if(!empty($choice)) {
            $chat_id = $this->callbackQuery->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();

            if($user->phone_number) {

            } else {
                $scenario = json_decode(Storage::get('first_scenario'));
                $scenario_move = $scenario->first;
                $template_path = 'bot.'.($user->language_code === 'ru'? 'ru.': 'en.').$scenario_move->template;
                $button = $user->language_code === 'ru'? 'Отправить локацию': 'Send location';

                $user->updateFields(['page' => 'first_scenario', 'step_id' => 1]);

                $this->chat->deleteMessage($this->messageId)->send();
                $this->chat
                    ->message(view($template_path, ['current_step' => $scenario_move->step_id, 'steps_amount' => $scenario->steps_amount]))
                    ->replyKeyboard(ReplyKeyboard::make()->button($button)->requestLocation())
                    ->send();
            }
        }

    }

    public function select_language(): void
    {
        $language_code = $this->data->get('choice');
        if(empty($language_code)) {

        }

        if(!empty($language_code)) {
            $chat_id = $this->callbackQuery->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();
            $user->updateFields(['language_code' => $language_code, 'page' => 'start']);

            $template_name = config('keyboards.start.template');
            $buttons = config('keyboards.start.buttons');
            $this->next_inline_page($this->messageId, $language_code, $template_name, $buttons);
        }
    }


    public function first_scenario(UserModel $user = null): void
    {
        $scenario = json_decode(Storage::get('first_scenario'));
        $step_id = $user->step_id;
        $template_path_lang = 'bot.'.($user->language_code === 'ru'? 'ru.': 'en.');

        if($step_id === 1) {
            $user->updateFields(['step_id' => 2]);
            $scenario_move = $scenario->second;
            $this->chat
                ->message(view(
                    $template_path_lang.$scenario_move->template,
                    ['step_id' => $scenario_move->step_id, 'steps_amount' => $scenario->steps_amount]))
                ->removeReplyKeyboard()
                ->send();
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $chat_id = $this->message->from()->id();
        $user = UserModel::where('chat_id', $chat_id)->first();

        if($user->page === 'first_scenario') {
            switch ($user->step_id) {
                case 1:
                    // #order добавление локации
                    $this->first_scenario($user);
                    break;
            }
        }
    }
}
