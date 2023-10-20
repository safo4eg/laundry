<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\File;
use App\Models\Ticket;
use App\Models\TicketItem;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;


trait SupportUserTrait
{
    use SupportTrait;
    public function close_ticket(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();
        $ticket->update([
            'status_id' => 4
        ]);
        $this->delete_ticket_card($this->chat, $ticket);
    }

    public function support_start(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.hello";
        $buttons = $this->config['support']['support'][$this->user->language_code];
        $button = $this->config['support']['tickets'][$this->user->language_code];

        if (!$this->user->message_id) {
            $response = $this->chat->message(view($template))->keyboard(Keyboard::make()->buttons([
                Button::make($buttons)->action("create_ticket"),
                Button::make($button)->action("check_user_tickets")
            ]))->send();
        } else {
            $response = $this->chat->edit($this->user->message_id)->message(view($template))->keyboard(Keyboard::make()->buttons([
                Button::make($buttons)->action("create_ticket"),
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
        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket_text";
        $button = $this->config['support']['back'][$this->user->language_code];
        $response = $this->chat->edit($this->user->message_id)
            ->message(view($template))
            ->keyboard(Keyboard::make()->buttons([
                Button::make($button)->action('support'),
            ]))
            ->send();

        $this->user->update([
            "step" => 2,
            "message_id" => $response->telegraphMessageId(),
            "page" => "ticket_creation"
        ]);
    }

    public function ticket_add_text_handler(): void
    {
        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        if ($this->message) {
            $ticket = Ticket::create([
                'user_id' => $this->user->id,
                'status_id' => 1
            ]);

            $this->bot->storage()->set('current_ticket_id', $ticket->id);

            $ticket_text = $this->message->text();

            TicketItem::create([
                'text' => $ticket_text,
                'ticket_id' => $ticket->id,
                'chat_id' => $this->message->from()->id()
            ]);

            $this->user->update([
                "step" => 3,
            ]);
        }

        $this->handle_ticket();
    }

    public function ticket_add_photo(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket_photo";
        $button = $this->config['support']['skip'][$this->user->language_code];
        $response = $this->chat->message(view($template))->keyboard(Keyboard::make()->buttons([
            Button::make($button)->action('ticket_created')->param('ticket_created_flag', 1)
        ]))->send();

        $this->user->update([
            "step" => 4,
            "message_id" => $response->telegraphMessageId()
        ]);

        $this->handle_ticket();
    }

    public function ticket_add_photo_handler(): void
    {
        $current_ticket = Ticket::where('user_id', $this->user->id)
            ->orderByDesc('time_start')
            ->first();
        $ticket_item = TicketItem::where('ticket_id', $current_ticket->id)
            ->orderByDesc('time')
            ->first();

        if (isset($this->message)) {
            if ($this->message->photos() !== null && $this->message->photos()->isNotEmpty()) {
                if (isset($this->user->message_id)) {
                    $this->delete_active_page();
                }

                $photos = $this->message->photos();
                $dir = "Ticket/ticket_item_{$ticket_item->id}";
                $photo = $this->save_ticket_photo($photos, $ticket_item);
                $file_name = "{$photo->id()}.jpg";

                $this->bot->storage()->set('photo_id', $photo->id());

                $confirmation_buttons = $this->config['support']['confirm'];
                $response = $this->chat->photo(Storage::path("{$dir}/{$file_name}"))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($confirmation_buttons['yes'][$this->user->language_code])
                                ->action('confirm_ticket_photo')
                                ->param('confirm', true),
                            Button::make($confirmation_buttons['no'][$this->user->language_code])
                                ->action('confirm_ticket_photo')
                                ->param('confirm', false),
                        ]))->send();

                $this->user->update([
                    'message_id' => $response->telegraphMessageId()
                ]);
            }
        }
    }

    public function confirm_ticket_photo(): void
    {
        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        $flag = $this->data->get('confirm');
        $current_ticket = Ticket::where('user_id', $this->user->id)
            ->orderByDesc('time_start')
            ->first();
        $ticket_item = TicketItem::where('ticket_id', $current_ticket->id)
            ->orderByDesc('time')
            ->first();

        if ($flag) {
            $dir = "ticket/ticket_item_{$ticket_item->id}";
            $photo_id = $this->bot->storage()->get('photo_id');
            $file_name = "$photo_id.jpg";

            $current_ticket->update([
                'status_id' => 2
            ]);
            File::create([
                'path' => Storage::url("{$dir}/{$file_name}"),
                'ticket_item_id' => $ticket_item->id,
            ]);
            $this->user->update([
                'step' => 5
            ]);
        } else {
            $this->user->update([
                "step" => 3,
            ]);
        }

        $this->handle_ticket();
    }


    public function ticket_created(): void
    {
        $flag = $this->data->get('ticket_created_flag');

        if ($flag) {
            $ticket_id = $this->bot->storage()->get('current_ticket_id');
            $ticket = Ticket::where('id', $ticket_id)->first();

            $ticket->update([
                'status_id' => 2
            ]);
        }

        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        $template = "{$this->template_prefix}{$this->user->language_code}.support.wait_answer";
        $button = $this->config['support']['tickets'][$this->user->language_code];
        $response = $this->chat
            ->message(view($template))
            ->keyboard(Keyboard::make()->buttons([
                Button::make($button)->action("check_user_tickets")
            ]))
            ->send();

        $this->user->update([
            "step" => 5,
            "message_id" => $response->telegraphMessageId()
        ]);
    }

// Просмотр тикетов пользователя
    public function check_user_tickets(): void
    {
        $tickets = $this->user->ticket();
        $type = $this->data->get('check_user_tickets');
        $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.info";
        $buttons = [
            'active' => $this->config['support']['lc']['active'][$this->user->language_code],
            'archive' => $this->config['support']['lc']['archive'][$this->user->language_code]
        ];
        $button = $this->config['support']['back'][$this->user->language_code];

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

        if (isset($type)) {
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
                        ->param('id', $active_ticket->id)
                        ->param('check_user_tickets', 'ticket_info')
                        ->width(0.5);
                }
                $buttons[] = Button::make($button)->action('check_user_tickets');

                $this->chat->edit($this->messageId)
                    ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                    ->keyboard(Keyboard::make()
                        ->buttons($buttons))->send();
            }

            if ($type == 'ticket_info') {
                $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.ticket_info";
                $ticket_id = $this->data->get('id');
                $ticket_items = Ticket::where('id', $ticket_id)
                    ->first()
                    ->ticketItems;

                $view = view($template, [
                    'ticket' => Ticket::where('id', $ticket_id)->first(),
                    'messages' => $ticket_items
                ]);

                $this->chat->edit($this->messageId)
                    ->message(preg_replace('#^\s+#m', '', $view))->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($button)->action('check_user_tickets')
                        ]))
                    ->send();
            }
        }
    }
}
