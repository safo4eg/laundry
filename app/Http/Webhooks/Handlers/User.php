<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\InlinePageTrait;
use App\Http\Webhooks\Handlers\Traits\UserTrait;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\Geo;
use DefStudio\Telegraph\Handlers\WebhookHandler;

use App\Models\User as UserModel;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;

use App\Services\Whatsapp;


class User extends WebhookHandler
{
    use InlinePageTrait, UserTrait;

    public function start(): void
    {
        $choice = $this->data->get('choice');

        if(empty($choice)) {
            $chat_id = $this->message->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();

            if($user) {
                if($user->page === 'menu') {
                    $language_code = $user->language_code;
                    $template_name = config('keyboards.start.template_name');
                    $buttons = config('keyboards.start.buttons');
                    $user->update(['page' => 'start']);
                    $this->send_inline_page($language_code, ['template_name' => $template_name], $buttons);
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
            Order::create(['user_id' => $user->id, 'status_id' => 1]);

            if($user->phone_number) {
                // если телефонный номер имеется значит на другой сценарий
            } else {
                $this->request_location($user, 'first_scenario', 'first');
            }
        }

    }

    public function select_language(): void
    {
        $language_code = $this->data->get('choice');
        if(empty($language_code)) {
            Log::debug('зашел сюда');
        }

        if(!empty($language_code)) {
            $chat_id = $this->callbackQuery->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();
            $user->update(['language_code' => $language_code, 'page' => 'start']);

            $template_name = config('keyboards.start.template_name');
            $buttons = config('keyboards.start.buttons');
            $this->next_inline_page($this->messageId, $language_code, ['template_name' => $template_name], $buttons);
        }
    }


    public function first_scenario(UserModel $user = null): void
    {
        $scenario = json_decode(Storage::get('first_scenario'));

        if(!$user) { // если юзер есть значит данные от message прилетели => достаем их из колбэкаКвери
            $chat_id = $this->callbackQuery->from()->id();
            $user = UserModel::where('chat_id', $chat_id)->first();
        }

        $step_id = $user->step_id;
        $template_path_lang = 'bot.'.($user->language_code === 'ru'? 'ru.': 'en.');
        $order = $user->getCurrentOrder();

        if($step_id === 1) {
            $location = $this->message->location();
            $y = $location->latitude();
            $x = $location->longitude();
            $geo = new Geo($x, $y);

            $order->update([
                'geo' => "$x,$y",
                'address' => $geo->address
            ]);

            $this->request_location_desc($user, 'first_scenario', 'second');
        } else if($step_id === 2) {
            $address_desc = $this->message->text();
            $order->update(['address_desc' => $address_desc]);

            $scenario_step = $scenario->third;
            $user->update(['step_id' => 3]);
            $button = $user->language_code === 'ru'? 'Отправить номер': 'Send number';
            $this->chat
                ->message(view(
                    $template_path_lang.$scenario_step->template,
                    ['step_id' => $scenario_step->step_id, 'steps_amount' => $scenario->steps_amount]))
                ->replyKeyboard(ReplyKeyboard::make()->button($button)->requestContact())
                ->send();
        } else if($step_id === 3) {
            $phone_number = $this->message->contact()->phoneNumber();
            $user->update(['phone_number' => $phone_number]);

            $scenario_step = $scenario->fourth;
            $user->update(['step_id' => 4]);
            $this->chat
                ->message(view(
                    $template_path_lang.$scenario_step->template,
                    ['step_id' => $scenario_step->step_id, 'steps_amount' => $scenario->steps_amount]))
                ->removeReplyKeyboard()
                ->send();
        } else if($step_id === 4) {
            $whatsapp_number = (int) $this->message->text();

            if($whatsapp_number) {
                $is_valid_whatsapp_number = (new Whatsapp())->check_account($whatsapp_number);
                Log::debug($is_valid_whatsapp_number);
                if($is_valid_whatsapp_number) {
                    $user->update(['whatsapp' => $whatsapp_number]);
                }
            }

            $this->request_accept_order($user, 'first_scenario', 'fifth');
        } else if($step_id === 5) {
            $this->request_order_accepted($user);
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $chat_id = $this->message->from()->id();
        $user = UserModel::where('chat_id', $chat_id)->first();

        switch ($user->page) {
            case 'first_scenario':
                $this->first_scenario($user);
                break;
        }
    }
}
