<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Order;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Support\Facades\Storage;

trait UserCommandsFuncsTrait
{
    public function check_for_incomplete_order(): bool
    {
        $order = Order::where('user_id', $this->user->id)
            ->where('status_id', 4)
            ->where('reason_id', 5)
            ->first();

        if (isset($order)) return true;
        else return false;
    }


    public function check_for_language_code(): bool
    {
        if (!isset($this->user) and isset($this->message)) {
            $command = $this->message->text();
            if (!preg_match('#^\/start#', $command)) {
                $this->chat
                    ->message('БД была обновлена, вызовите команду /start')
                    ->send();
                return true;
            }
            return false;
        } else if (!isset($this->user->language_code)) {
            $this->select_language();
            return true;
        }
        return false;
    }

    public function terminate_active_page(bool $disable_active_order = true): void
    {
        $page = $this->user->page;

        switch ($page) {
            case 'first_scenario':
            case 'second_scenario':
            case 'first_scenario_phone':
            case 'first_scenario_whatsapp':
                $this->terminate_filling_order($this->user->active_order);
                break;
            default:
                $this->delete_active_page_message();
        }

        if ($disable_active_order) {
            $active_order = $this->user->active_order;
            if (isset($active_order)) $active_order->update(['active' => 0]);
        }
    }

    private function terminate_filling_order(Order $order): void
    {
        $pause_template = $this->template_prefix . $this->user->language_code . '.order.pause';

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


    public function terminate_filling_ticket(Ticket $ticket): void
    {
        $pause_template = $this->template_prefix . $this->user->language_code . '.support.pause';
        $this->chat->message(view($pause_template))->send();
        $ticket->update([
            'last_step' => $this->user->step
        ]);
        $this->user->update([
            'step' => null
        ]);
    }

    private function delete_active_page_message(): void
    {
        if (isset($this->user->message_id)) {
            $this->chat->deleteMessage($this->user->message_id)->send();
            $this->user->update([
                'page' => null,
                'message_id' => null
            ]);
        }
    }

    public function save_photo(\Illuminate\Support\Collection $photos, Order $order = null)
    {
        $photo = $photos->last(); // лучшее качество фото
        $dir = "User/{$this->user->id}/";
        $file_name = $photo->id() . ".jpg";

        switch ($this->user->page) {
            case 'payment_photo':
                $dir = $dir . "payments/{$order->payment->id}";
        }

        Telegraph::store($photo, Storage::path($dir), $file_name);

        return $photo;
    }
}
