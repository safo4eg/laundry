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
        $template_path = 'bot.' . ($user->language_code === 'ru' ? 'ru.' : 'en.') . $scenario_step->template;
        $button = $user->language_code === 'ru' ? 'Отправить локацию' : 'Send location';

        $user->update([
            'page' => $scenario_name,
            'step_id' => 1
        ]);

        if ($scenario_name === 'first_scenario') {
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
        $template_path = 'bot.' . ($user->language_code === 'ru' ? 'ru.' : 'en.') . $scenario_step->template;

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
        $scenario = json_decode(Storage::get($scenario_name));
        $scenario_step = $scenario->$scenario_step;

        $user->update(['step_id' => $scenario_step->step_id]);

        $this->sendMessage($scenario_step->template, [
            'step_id' => $scenario_step->step_id,
            'steps_amount' => $scenario->steps_amount
        ]);
    }

    public function request_order_accepted(User $user): void
    {
        $order = $user->getCurrentOrder();
        $user->update(['page' => 'order_accepted', 'step_id' => null]);
        $order->update(['status_id' => 2]);

        $this->sendMessage('order_accepted', [
           'order_id' => $order->id
        ]);
    }
}
