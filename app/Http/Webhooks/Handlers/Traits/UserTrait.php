<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\User;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Storage;

trait UserTrait
{
    public function request_location(User $user, string $scenario_name, string $scenario_step): void
    {
        $scenario = json_decode(Storage::get($scenario_name));
        $scenario_step = $scenario->$scenario_step;
        $template_path = 'bot.'.($user->language_code === 'ru'? 'ru.': 'en.').$scenario_step->template;
        $button = $user->language_code === 'ru'? 'Отправить локацию': 'Send location';

        $user->update(['page' => $scenario_name, 'step_id' => 1]);

        if($scenario_name === 'first_scenario') {
            $this->chat->deleteMessage($this->messageId)->send();
        }

        $this->chat
            ->message(view($template_path, ['step_id' => $scenario_step->step_id, 'steps_amount' => $scenario->steps_amount]))
            ->replyKeyboard(ReplyKeyboard::make()->button($button)->requestLocation())
            ->send();
    }

    public function request_location_desc(User $user, string $scenario_name, string $scenario_step): void
    {
        $scenario = json_decode(Storage::get($scenario_name));
        $scenario_step = $scenario->$scenario_step;
        $template_path = 'bot.'.($user->language_code === 'ru'? 'ru.': 'en.').$scenario_step->template;

        $user->update(['step_id' => 2]);

        $this->chat
            ->message(view(
                $template_path,
                ['step_id' => $scenario_step->step_id, 'steps_amount' => $scenario->steps_amount]))
            ->removeReplyKeyboard()
            ->send();
    }

    public function request_accept_order(User $user, string $scenario_name, string $scenario_step): void
    {
        $keyboards = config('keyboards')['accept_order'];
        $scenario = json_decode(Storage::get($scenario_name));

        $scenario_step = $scenario->$scenario_step;
        $buttons = $keyboards['buttons'];
        $language_code = $user->language_code;

        $user->update(['step_id' => $scenario_step->step_id]);

        $this->send_inline_page(
            $language_code,
            [
                'template_name' => $scenario_step->template,
                'vars' => ['step_id' => $scenario_step->step_id, 'steps_amount' => $scenario->steps_amount]
            ],
            $buttons
        );
    }

    public function request_order_accepted(User $user): void
    {
        $keyboards = config('keyboards')['order_accepted'];
        $order = $user->getCurrentOrder();

        $buttons = $keyboards['buttons'];
        $template_name = $keyboards['template_name'];
        $language_code = $user->language_code;

        $user->update(['page' => 'order_accepted', 'step_id' => null]);
        $order->update(['status_id' => 2]);

        $this->next_inline_page(
            $this->messageId,
            $language_code,
            [
                'template_name' => $template_name,
                'params' => ['order_id' => $order->id]
            ],
            $buttons
        );
    }

}
