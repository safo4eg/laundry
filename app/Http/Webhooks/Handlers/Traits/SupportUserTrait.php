<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use App\Models\File;
use App\Models\Ticket;
use App\Models\TicketItem;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;


trait SupportUserTrait
{
    use SupportTrait;
    use UserCommandsFuncsTrait;

    public function get_support_answer(): void
    {
        $this->terminate_active_page();

        $ticket_item_id = $this->data->get('ticket_item_id');
        $ticket_item = TicketItem::where('id', $ticket_item_id)->first();

        $view = view('bot.support.send_user_answer', [
            'text' => $ticket_item->text,
            'ticket_id' => $ticket_item->ticket_id
        ]);
        $buttons = config('buttons.user')['support']['get_answer'];
        $keyboard = Keyboard::make()->buttons([
            Button::make($buttons['have_questions'][$this->user->language_code])
                ->action('add_ticket')
                ->param('ticket_id', $ticket_item->ticket_id),
            Button::make($buttons['have_not_questions'][$this->user->language_code])
                ->action('close_ticket')
                ->param('ticket_id', $ticket_item->ticket_id)
        ]);

        $response = $this->chat->message($view)->keyboard($keyboard)->send();
        $this->user->update([
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function close_ticket(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();
        $ticket->update([
            'status_id' => 4
        ]);

        $support_chat = Chat::where('name', 'Support')->first();
        $this->delete_ticket_card($support_chat, $ticket);

        $view = "bot.user.{$ticket->user->language_code}.support.rate";
        $buttons = config('buttons.user')['support']['close_ticket'];
        $buttons = [
            Button::make($buttons['new_order'])->action('start'),
            Button::make($buttons['new_request'])->action('support')
        ];

        $this->chat->edit($this->user->message_id)
            ->message(view($view))
            ->keyboard(Keyboard::make()->buttons($buttons))
            ->send();
    }

    public function support_start(): void
    {
        $template = "{$this->template_prefix}{$this->user->language_code}.support.hello";
        $buttons = $this->config['support']['support'][$this->user->language_code];
        $button = $this->config['support']['tickets'][$this->user->language_code];


        $response = $this->chat->message(view($template))->keyboard(Keyboard::make()->buttons([
            Button::make($buttons)->action("create_ticket"),
            Button::make($button)->action("check_user_tickets")
        ]))->send();


        $this->user->update([
            "page" => "support",
            "step" => 1,
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function create_ticket(): void
    {
        $flag = $this->data->get('choice');
        $user = $this->callbackQuery->from();

        if ($this->user->page === 'support') {
            if (!$flag) {
                if ($this->check_incomplete_tickets($user)) return;
            }
        }

        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket_text";
        $button = $this->config['support']['back'][$this->user->language_code];

        $buttons = [
            Button::make($button)->action('support'),
        ];

        $response = $this->chat->edit($this->user->message_id)
            ->message(view($template))
            ->keyboard(Keyboard::make()->buttons($buttons))
            ->send();

        $this->user->update([
            "message_id" => $response->telegraphMessageId(),
            "page" => "ticket_creation"
        ]);
    }

    public function ticket_add_text_handler(): void
    {
        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page_message();
        }

        if ($this->message) {
            $user = $this->message->from();
            if ($user->storage()->get('current_ticket_id')) {
                $ticket_text = $this->message->text();
                TicketItem::create([
                    'text' => $ticket_text,
                    'ticket_id' => $user->storage()->get('current_ticket_id'),
                    'chat_id' => $this->message->from()->id()
                ]);
            } else {
                $ticket = Ticket::create([
                    'user_id' => $this->user->id,
                    'status_id' => 1
                ]);
                $user->storage()->set('current_ticket_id', $ticket->id);

                $ticket_text = $this->message->text();
                TicketItem::create([
                    'text' => $ticket_text,
                    'ticket_id' => $ticket->id,
                    'chat_id' => $this->message->from()->id()
                ]);

            }
            $this->user->update([
                "step" => 2,
            ]);
        }

        $this->handle_ticket_request();
    }

    public function ticket_add_photo(): void
    {
        if ($this->user->message_id) {
            $this->chat->deleteMessage($this->user->message_id)->send();
        }

        $template = "{$this->template_prefix}{$this->user->language_code}.support.create_ticket_photo";
        $button = $this->config['support']['skip'][$this->user->language_code];
        $response = $this->chat->message(view($template))->keyboard(Keyboard::make()->buttons([
            Button::make($button)->action('ticket_created')->param('ticket_created_flag', 1)
        ]))->send();

        $this->user->update([
            "message_id" => $response->telegraphMessageId()
        ]);
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
                    $this->delete_active_page_message();
                }

                $user = $this->message->from();

                $photos = $this->message->photos();
                $message_timestamp = $this->message->date()->timestamp; // время отправки прилетевшего фото
                $last_message_timestamp = $user->storage()->get('photo_message_timestamp'); // timestamp предыдущего прилетевшего фото

                if ($message_timestamp !== $last_message_timestamp) {
                    $photo = $this->save_ticket_photo($photos, $ticket_item);
                    $user->storage()->set('photo_id', $photo->id());
                }

                $user->storage()->set('photo_message_timestamp', $message_timestamp);

                $dir = "Ticket/ticket_item_{$ticket_item->id}";
                $file_name = "{$photo->id()}.jpg";

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
            $this->delete_active_page_message();
        }

        $flag = $this->data->get('confirm');

        if ($this->message) {
            $user = $this->message->from();
            $current_ticket = $user->storage()->get('current_ticket_id');
            $user->storage()->forget('current_ticket_id');
            $ticket_item = TicketItem::where('ticket_id', $current_ticket)->first();

            if ($flag) {
                $dir = "ticket/ticket_item_{$ticket_item->id}";

                $photo_id = $user->storage()->get('photo_id');

                $file_name = "$photo_id.jpg";

                $ticket = Ticket::where('id', $current_ticket)->first();
                $ticket->update([
                    'status_id' => 2
                ]);

                File::create([
                    'path' => Storage::url("{$dir}/{$file_name}"),
                    'ticket_item_id' => $ticket_item->id,
                ]);
                $this->user->update([
                    'step' => null
                ]);

                $this->ticket_created($user);
            } else {
                $this->user->update([
                    "step" => 2,
                ]);
            }
        }
    }


    public function ticket_created(): void
    {
        $flag = $this->data->get('ticket_created_flag');

        if ($flag) {
            if ($this->message){
                $user = $this->message->from();
            } elseif ($this->callbackQuery){
                $user = $this->callbackQuery->from();
            }
            $ticket_id = $user->storage()->get('current_ticket_id');
            $user->storage()->forget('current_ticket_id');
            $ticket = Ticket::where('id', $ticket_id)->first();

            $chat = Chat::where('name', 'Support')->first();
            $this->send_ticket_card($chat, $ticket);

            $ticket->update([
                'status_id' => 2
            ]);
        }

        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page_message();
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
            "step" => null,
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
                $this->ticket_list($archive_tickets, 'archive');
            }
            if ($type == 'active') {
                $active_tickets = $tickets->whereNull('time_end')->get();
                $this->ticket_list($active_tickets, 'active');
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
                $new_req_button = $this->config['support']['new_request'][$this->user->language_code];

                $this->chat->edit($this->messageId)
                    ->message(preg_replace('#^\s+#m', '', $view))->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($button)->action('check_user_tickets'),
                            Button::make($new_req_button)
                                ->action('add_ticket')
                                ->param('ticket_id', $ticket_id)
                        ]))
                    ->send();
            }
        }
    }


    public function ticket_list($tickets, string $type): void
    {
        $button = $this->config['support']['back'][$this->user->language_code];
        $template = "{$this->template_prefix}{$this->user->language_code}.support.lc.$type";
        $view = view($template, [
            'tickets' => $tickets
        ]);

        $buttons = [];
        foreach ($tickets as $active_ticket) {
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


    public function add_ticket(): void
    {
        $ticket_id = $this->data->get('ticket_id');
        if ($this->callbackQuery) {
            $user = $this->callbackQuery->from();
            $user->storage()->set('current_ticket_id', $ticket_id);
            $this->user->update([
                'page' => 'add_ticket',
                'step' => 1
            ]);
        }

        $this->handle_ticket_request();
    }

    public function check_incomplete_tickets($user = null): bool
    {
        $flag = $this->data->get('choice');

        if (!$flag) {
            if ($user->storage()->get('current_ticket_id')) {
                $view = "{$this->template_prefix}{$this->user->language_code}.support.incomplete_ticket";
                $buttons = $this->config['support']['check_incomplete_tickets'];
                $response = $this->chat->edit($this->user->message_id)
                    ->message(view($view))->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($buttons['yes'][$this->user->language_code])->action('check_incomplete_tickets')->param('choice', 1),
                            Button::make($buttons['no'][$this->user->language_code])->action('check_incomplete_tickets')->param('choice', 2)
                        ]))->send();

                $this->user->update([
                    'message_id' => $response->telegraphMessageId()
                ]);
                return true;
            }
        } elseif ($flag == 2) {
            $user = $this->callbackQuery->from();
            $user->storage()->forget('current_ticket_id');
            $this->create_ticket();
        } elseif ($flag == 1) {
            $user = $this->callbackQuery->from();
            $ticket = Ticket::where('id', $user->storage()->get('current_ticket_id'))->first();
            $this->user->update([
                "step" => $ticket->last_step
            ]);
            $this->handle_ticket_request();
        }
        return false;
    }
}
