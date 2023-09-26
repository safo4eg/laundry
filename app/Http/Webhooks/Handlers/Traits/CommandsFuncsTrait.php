<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Order;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait CommandsFuncsTrait
{
    public function terminate_filling_order(Order $order): void
    {
        $pause_template = $this->template_prefix.$this->user->language_code.'.order.pause';

        $order->update([
            'status_id' => 4,
            'reason_id' => 5,
            'last_step' => $this->user->step,
            'active' => false
        ]);

        $this->user->update(['step' => null]);

        $this->chat
            ->message(trim((string)view($pause_template, ['orders' => $order]), " \n\r\t\v\x"))
            ->removeReplyKeyboard()
            ->send();
    }

    public function delete_active_page(): void
    {
        $this->chat->deleteMessage($this->user->message_id)->send();
        $this->user->update([
            'message_id' => null
        ]);
    }
}
