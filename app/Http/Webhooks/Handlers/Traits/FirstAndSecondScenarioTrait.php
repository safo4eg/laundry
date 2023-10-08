<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Services\Geo;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;

trait FirstAndSecondScenarioTrait
{
    public function request_geo(int $step, int $steps_amount): void
    {
        $button = $this->config['send_location'][$this->user->language_code];
        $template = $this->template_prefix.$this->user->language_code.".order.geo";
        $this->chat->message((string) view(
            $template,
            [
                'step' => $step,
                'steps_amount' => $steps_amount
            ]))
            ->replyKeyboard(ReplyKeyboard::make()
                ->button($button)->requestLocation()->resize())
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
                ->button($button)
                    ->requestContact()
                    ->resize())
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
        $response = $this->chat
            ->message((string) view($template, [
                'step' => $step,
                'steps_amount' => $steps_amount]))
            ->keyboard(Keyboard::make()
                ->button($button)
                ->action('order_accepted_handler')
                ->param('order_accepted_handler', 1))
            ->send();

        $this->user->update([
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function order_accepted_handler(): void
    {
        $flag = $this->data->get('order_accepted_handler');
        $order = $this->user->active_order;

        $buttons = [
            'wishes' => $this->config['order_accepted']['wishes'][$this->user->language_code],
            'cancel' => $this->config['order_accepted']['cancel'][$this->user->language_code],
            'recommend' => $this->config['order_accepted']['recommend'][$this->user->language_code],
        ];
        $template = $this->template_prefix.$this->user->language_code.".order.accepted";
        $keyboard = Keyboard::make()->buttons([
            Button::make($buttons['wishes'])
                ->action('write_order_wishes')
                ->param('write_order_wishes', 1),
            Button::make($buttons['cancel'])
                ->action('cancel_order')
                ->param('cancel_order', 1),
            Button::make($buttons['recommend'])
                ->action('referrals')
        ]);

        $response = null;
        if(isset($flag)) {
            if($order->status_id < 2) { // если статус заказа больше 2
                $order->update([ // в этот момент заказ улетает в MANAGER CHAT
                    'status_id' => 2
                ]);
            }

            $response = $this->chat->edit($this->messageId)
                ->message((string) view($template, [
                    'order_id' => $order->id
                ]))
                ->keyboard($keyboard)
                ->send();
        }

        if(!isset($flag)) {
            $this->chat->deleteMessage($this->user->message_id)->send();
            $response = $this->chat
                ->message((string) view($template, [
                    'order_id' => $order->id
            ]))
                ->keyboard($keyboard)
                ->send();
        }

        $this->user->update([
            'page' => 'order_accepted',
            'step' => null,
            'message_id' => $response->telegraphMessageId()
        ]);
    }
}
