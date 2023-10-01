<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Ticket;
use App\Models\TicketItem;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;


trait SupportTrait
{
    public function support_start(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.hello";
        $buttons = $this->config['support'][$this->user->language_code];
        $button = $this->config['tickets'][$this->user->language_code];

        if (!$this->user->message_id){
            $response = $this->chat->message(view($template))->keyboard(Keyboard::make()->buttons([
                Button::make($buttons)->action("support")->param('support', 1),
                Button::make($button)->action("check_user_tickets")
            ]))->send();
        }
        else {
            $response = $this->chat->edit($this->user->message_id)->message(view($template))->keyboard(Keyboard::make()->buttons([
                Button::make($buttons)->action("support")->param('support', 1),
                Button::make($button)->action("check_user_tickets")
            ]))->send();
        }

        $this->user->update([
            "page" => "support",
            "step" => 1,
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function create_ticket(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket";
        $button = $this->config['back'][$this->user->language_code];
        $response = $this->chat->edit($this->user->message_id)
            ->message(view($template))
            ->send();

        $this->user->update([
            "step" => 2,
            "message_id" => $response->telegraphMessageId()
        ]);
    }

    public function ticket_created(): void
    {
        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        $template = "{$this->template_prefix}{$this->user->language_code}.support.wait_answer";


        $ticket = Ticket::create([
            'user_id' => $this->user->id,
            'status_id' => 1
        ]);

        if ($this->message) {
            $ticket_text = $this->message->text();
            TicketItem::create([
                'text' => $ticket_text,
                'ticket_id' => $ticket->id,
                'chat_id' => $this->message->from()->id()
            ]);
        }

        if ($ticket) {
            $button = $this->config['tickets'][$this->user->language_code];

            $response = $this->chat
                ->message(view($template, [
                    'id' => $ticket->id
                ]))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make($button)->action('check_user_tickets')
                ]))
                ->send();

            $this->user->update([
                "step" => 3,
                "message_id" => $response->telegraphMessageId()
            ]);
        }
    }


    // TODO: Просмотр заявок пользователя
    public function check_user_tickets(): void
    {
        $tickets = $this->user->ticket();
        $type = $this->data->get('check_user_tickets');
        $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.info";
        $buttons = [
            'active' => $this->config['lc']['active'][$this->user->language_code],
            'archive' => $this->config['lc']['archive'][$this->user->language_code]
        ];
        $button = $this->config['back'][$this->user->language_code];


        $this->user->update([
            'page' => 'check_user_tickets',
            'step' => null
        ]);

        if (!isset($type)) {
            $this->chat->edit($this->messageId)->message(view($template))->keyboard(
                Keyboard::make()->buttons([
                    Button::make($buttons['active'])->action('check_user_tickets')
                        ->param('check_user_tickets', 'active')->width(0.5),
                    Button::make($buttons['archive'])->action('check_user_tickets')
                        ->param('check_user_tickets', 'archive')->width(0.5),
                    Button::make($button)->action('support')
                ])
            )->send();
        }

        if(isset($type)) {
            if ($type == 'archive') {
                $archive_tickets = $tickets->whereNotNull('time_end')->get();
                $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.archive";
                $view = view($template, [
                    'tickets' => $archive_tickets
                ]);

                $buttons = [];
                foreach ($archive_tickets as $archive_ticket) {
                    $buttons[] = Button::make("#{$archive_ticket->id}")->action('check_user_tickets')
                        ->param('id', $archive_ticket->id)->param('check_user_tickets', 'ticket_info')->width(0.5);
                }
                $buttons[] = Button::make($button)->action('check_user_tickets');

                $this->chat->edit($this->messageId)
                    ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                    ->keyboard(Keyboard::make()
                        ->buttons($buttons))->send();
            }

            if ($type == 'active') {
                $active_tickets = $tickets->whereNull('time_end')->get();
                $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.active";
                $view = view($template, [
                    'tickets' => $active_tickets
                ]);

                $buttons = [];
                foreach ($active_tickets as $active_ticket) {
                    $buttons[] = Button::make("#{$active_ticket->id}")->action('check_user_tickets')
                        ->param('id', $active_ticket->id)->param('check_user_tickets', 'ticket_info')->width(0.5);
                }
                $buttons[] = Button::make($button)->action('check_user_tickets');

                $this->chat->edit($this->messageId)
                    ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                    ->keyboard(Keyboard::make()
                        ->buttons($buttons))->send();
            }

            if ($type == 'ticket_info') {
                $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.ticket_info";

                $this->chat->edit($this->messageId)
                    ->message(view($template))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($button)->action('check_user_tickets')
                        ]))
                    ->send();
            }
        }
    }
}
