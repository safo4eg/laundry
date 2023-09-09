<?php

namespace App\Http\Webhooks\Handlers\Traits;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

trait UserTrait
{
    public function send_start(UserModel $user, int $message_id = null): void
    {
        if(!is_null($message_id)) {

        }

        $language_code = $user->language_code;
        $buttons = ['start' => null, 'reviews' => null];

        if($language_code === 'en') {
            $buttons['start'] = 'Start';
            $buttons['reviews'] = 'Reviews';
        }

        if($language_code === 'ru') {
            $buttons['start'] = 'Начать';
            $buttons['reviews'] = 'Отзывы';
        }

        $this->chat->message(view("bot.$language_code.start"))->keyboard(Keyboard::make()->buttons([
            Button::make($buttons['start'])->action('scenario')->param('any', 'data'),
            Button::make($buttons['reviews'])->url('https://t.me/laundrybot_feedback')
        ]))->send();
    }

    public function send_select_language(): void
    {
        $this->chat->message(view('bot.select_language'))
            ->keyboard(Keyboard::make()->buttons([
                Button::make('English')->action('select_language')->param('language_code', 'en'),
                Button::make('Русский')->action('select_language')->param('language_code', 'ru')
            ]))->send();
    }
}
