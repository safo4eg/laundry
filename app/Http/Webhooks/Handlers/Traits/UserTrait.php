<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Services\Geo;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;

trait UserTrait
{
    public function request_geo(int $step, int $steps_amount): void
    {
        $button = $this->config['send_location'][$this->user->language_code];
        $template = $this->template_prefix.$this->user->language_code.".order.geo";
        $this->chat->deleteMessage($this->messageId)->send(); // здесь возможно нужно будет условие page
        $this->chat->message((string) view(
            $template,
            [
                'step' => $step,
                'steps_amount' => $steps_amount
            ]))
            ->replyKeyboard(ReplyKeyboard::make()
                ->button($button)->requestLocation())
            ->send();
    }

    public function geo_handler():void
    {
        $location = $this->message->location();
        if($location) {
            $order = $this->user->active_order;

            $y = $location->latitude();
            $x = $location->longitude();
            $geo = new Geo($x, $y);

            $order->update([
                'geo' => "$x,$y",
                'address' => $geo->address
            ]);

            $this->user->update([
                'step' => ++$this->user->step
            ]);

            $this->handle_scenario_request();
        }
    }

    public function request_address_desc(int $step, int $steps_amount): void
    {
        $template = $this->template_prefix.$this->user->language_code.".order.address_desc";
        $this->chat->message((string) view(
            $template,
            [
                'step' => $step,
                'steps_amount' => $steps_amount
            ]
        ))->removeReplyKeyboard()->send();
    }

    public function address_desc_handler(): void
    {
        $address_desc = $this->message->text();
        $order = $this->user->active_order;

        $order->update([
            'address_desc' => $address_desc
        ]);

        $this->user->update([
            'step' => ++$this->user->step
        ]);

        $this->handle_scenario_request();
    }

    public function request_contact(int $step, int $steps_amount): void
    {
        $button = $this->config['send_contact'][$this->user->language_code];
        $template = $this->template_prefix.$this->user->language_code.".order.contact";
        $this->chat->message((string) view(
            $template,
            ['step' => $step, 'steps_amount' => $steps_amount]
        ))
            ->replyKeyboard(ReplyKeyboard::make()
                ->button($button)->requestContact())
            ->send();
    }

    public function contact_handler(): void
    {
        $contact = $this->message->contact();
        if($contact) {
            $phone_number = $contact->phoneNumber();
            $this->user->update([
                'phone_number' => $phone_number,
                'step' => ++$this->user->step
            ]);

            $this->handle_scenario_request();
        }
    }

    public function request_whatsapp(int $step, int $steps_amount): void
    {
        $template = $this->template_prefix.$this->user->language_code.".order.whatsapp";
        $this->chat->message((string) view(
            $template,
            [
                'step' => $step,
                'steps_amount' => $steps_amount
            ]))
            ->removeReplyKeyboard()
            ->send();
    }

    public function whatsapp_handler(): void
    {
        $whatsapp_number = $this->message->text();

        if(mb_strlen($whatsapp_number) >= 32) {
            $whatsapp_number = null;
        } else {
            $whatsapp_number = ((int) $whatsapp_number)? $whatsapp_number: null;
        }

        $this->user->update([
            'whatsapp' => $whatsapp_number,
            'step' => ++$this->user->step
        ]);

        $this->handle_scenario_request();
    }

    public function request_accepted_order(int $step, int $steps_amount): void
    {
        $button = $this->config['accept_order'][$this->user->language_code];
        $template = $this->template_prefix.$this->user->language_code.".order.accept";
        $this->chat
            ->message((string) view($template, [
                'step' => $step,
                'steps_amount' => $steps_amount]))
            ->keyboard(Keyboard::make()
                ->button($button)->action('order_accepted_handler')->param('choice', 1))
            ->send();
    }

    public function order_accepted_handler(): void
    {
        $choice = $this->data->get('choice');

        if($choice) {
            $order = $this->user->active_order;
            $order->update([ // в этот момент заказ улетает в ADMIN CHAT
                'status_id' => 2
            ]);

            $buttons = [
                'wishes' => $this->config['order_accepted']['wishes'][$this->user->language_code],
                'cancel' => $this->config['order_accepted']['cancel'][$this->user->language_code],
                'recommend' => $this->config['order_accepted']['recommend'][$this->user->language_code],
            ];
            $template = $this->template_prefix.$this->user->language_code.".order.accepted";
            $response = $this->chat->edit($this->messageId)
                ->message((string) view($template, [
                    'order_id' => $order->id
                ]))
                ->keyboard(Keyboard::make()
                    ->buttons([
                        Button::make($buttons['wishes'])
                            ->action('write_order_wishes')
                            ->param('choice', 1),
                        Button::make($buttons['cancel'])
                            ->action('cancel_order')
                            ->param('choice', 1),
                        Button::make($buttons['recommend'])->action('ref')
                    ]))
                ->send();

            $this->user->update([
                'page' => 'order_accepted',
                'step' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }
    }
}
