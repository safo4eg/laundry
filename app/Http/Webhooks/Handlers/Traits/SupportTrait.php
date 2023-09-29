<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Ticket;
use App\Models\TicketItem;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;


trait SupportTrait
{
    public function support_start(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.hello";
        $buttons = $this->config['support'][$this->user->language_code];
        $response = $this->chat->edit($this->user->message_id)->message(view($template))->keyboard(Keyboard::make()->buttons([
            Button::make($buttons)->action("support")->param('support', 1),
            Button::make('Посмотреть прошлые заявки')->action("check_user_tickets")
        ]))->send();

        $this->user->update([
            "page" => "support",
            "step" => 1,
        ]);
    }

    public function create_ticket(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket";
        $response = $this->chat->edit($this->messageId)
            ->message(view($template))
            ->send();

        $this->user->update([
            "step" => 2,
            "message_id" => $response->telegraphMessageId()
        ]);
    }

    public function ticket_created(): void
    {
        $this->user->update([
            "step" => 3,
        ]);

        $template = "{$this->template_prefix}{$this->user->language_code}.support.wait_answer";

        $ticket_text = $this->message->text();

        $ticket = Ticket::create([
            'user_id' => $this->user->id,
            'status_id' => 1
        ]);

        if ($ticket_text) {
            $ticket_item = TicketItem::create([
                'text' => $ticket_text,
                'ticket_id' => $ticket->id,
                'chat_id' => $this->message->from()->id()
            ]);
        }

        if ($ticket) {
            $this->chat->edit($this->user->message_id)
                ->message(view($template, [
                    'id' => $ticket->id
                ]))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make('Проверить статус заявки')->action('check_user_tickets')
                ]))
                ->send();
        }
    }


    // TODO: Просмотр заявок пользователя
    public function check_user_tickets(): void
    {
        $tickets = $this->user->tickets();
        $type = $this->data->get('check_user_tickets');
        $template = "{$this->template_prefix}{$this->user->language_code}.support.check_ticket_status.check_info";

        $this->user->update([
            'page' => 'check_user_tickets',
            'step' => 0
        ]);

        if (empty($type)) {
            $this->chat->message('Тут вы можете посмотреть тикеты свои')->keyboard(
                Keyboard::make()->buttons([
                    Button::make('Активные запросы')->action('check_user_tickets')
                        ->param('check_user_tickets', 'active'),
                    Button::make('Архивные запросы')->action('check_user_tickets')
                        ->param('check_user_tickets', 'archive'),
                ])
            )->send();
        }

        if ($type == 'archive') {
            $archive_tickets = $tickets->whereNotNull('time_end')->get();
            $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.archive";

            $buttons = [];
            foreach ($archive_tickets as $archive_ticket) {
                $buttons[] = Button::make("#{$archive_ticket->id}")->action('check_user_tickets')
                    ->param('ticket_info', $archive_ticket->id)->width(0.5);
            }

            $buttons[] = Button::make('Назад');

            $this->chat->message(view($template, [
                'tickets' => $archive_tickets
            ]))->keyboard(Keyboard::make()->buttons($buttons))->send();
        }

        if ($type == 'active') {
            $active_tickets = $tickets->whereNull('time_end')->get();
            $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.active";

            $buttons = [];
            foreach ($active_tickets as $active_ticket) {
                $buttons[] = Button::make("#{$active_ticket->id}")->action('check_user_tickets')
                    ->param('ticket_info', $active_ticket->id)->width(0.5);
            }

            $buttons[] = Button::make('Назад');

            $this->chat->message(view($template, [
                'tickets' => $active_tickets
            ]))->keyboard(Keyboard::make()->buttons($buttons))->send();
        }
    }
}
