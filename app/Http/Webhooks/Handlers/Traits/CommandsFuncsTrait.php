<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Order;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait CommandsFuncsTrait
{
    public function check_for_incomplete_order(): bool
    {
        $order = Order::where('user_id', $this->user->id)
            ->where('status_id', 4)
            ->where('reason_id', 5)
            ->first();

        if(isset($order)) return true;
        else return false;
    }

    public function check_for_language_code(): bool
    {
        if(!isset($this->user) and isset($this->message)) {
            if($this->message->text() !== '/start') {
                $this->start();
                return true;
            }
        } else if (!isset($this->user->language_code)) {
            $this->select_language();
            return true;
        }
        return false;
    }
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
