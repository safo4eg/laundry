<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\ChatOrder;
use App\Models\Ticket;
use App\Models\TicketItem;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait SupportTrait
{
    public function confirm_answer($text = null, Ticket $ticket = null): void
    {
        $flag = $this->data->get('choice');
        if (isset($flag)) {
            if ($flag == 1) {

                $ticket_id = $this->data->get('ticket_id');

                $ticket_item = TicketItem::create([
                    'text' => $this->chat->storage()->get('text'),
                    'ticket_id' => $ticket_id,
                    'chat_id' => $this->chat->chat_id
                ]);

                $ticket = Ticket::where('id', $ticket_id)->first();
                $this->delete_message_by_types([12]);
                $this->delete_ticket_card($ticket);
            } elseif ($flag == 2) {
                $this->delete_message_by_types([6]);
                $this->answer();
            }

            if ($ticket->status_id !== 3) {
                $ticket->update([
                    'status_id' => 3
                ]);
            } else {
                $this->send_ticket_card($this->chat, $ticket);
            }

            $user = $ticket->user;
            $view = view('bot.support.send_user_answer', [
                'text' => $ticket_item->text,
                'ticket_id' => $ticket_id
            ]);

            // TODO: Доделать вид карточки для юзера
            $keyboard = Keyboard::make()->buttons([
                Button::make('У меня остались еще вопросы')->action('')
            ]);

            $this->send_message_to_user($user->chat_id, $view);
        } else {
            $response = $this->chat->message("Ваш ответ - $text")
                ->keyboard(Keyboard::make()->buttons([
                    Button::make('Yes')->action('confirm_answer')
                        ->param('ticket_id', $ticket->id)
                        ->param('choice', 1)->width(0.5),
                    Button::make('No')->action('confirm_answer')
                        ->param('ticket_id', $ticket->id)
                        ->param('choice', 2)->width(0.5),
                    Button::make('Cancel')->action('cancel')
                ]))
                ->send();

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'message_id' => $response->telegraphMessageId(),
                'ticket_id' => $ticket->id,
                'message_type_id' => 12
            ]);
        }
    }

    public function send_ticket_card($chat, $ticket): void
    {
        $view = view('bot.support.ticket_card', [
            'ticket' => $ticket,
            'baseUrl' => url()
        ]);

        $buttons = [
            Button::make('Отклонить')->action('reject')->param('ticket_id', $ticket->id)
        ];

        if ($ticket->status < 3) {
            $buttons[] = Button::make('Ответить')->action('answer')->param('ticket_id', $ticket->id);
        } else {
            Button::make('Дополнить ответ')->action('answer')->param('ticket_id', $ticket->id);
        }

        $keyboard = Keyboard::make()->buttons($buttons);

        $response = $chat->message(preg_replace('#^\s+#m', '', $view))
            ->keyboard($keyboard)
            ->send();

        ChatOrder::create([
            'telegraph_chat_id' => $chat->id,
            'message_id' => $response->telegraphMessageId(),
            'ticket_id' => $ticket->id,
            'message_type_id' => 9
        ]);
    }


    public function delete_ticket_card(Ticket $ticket): void
    {
        $messages = ChatOrder::where('telegraph_chat_id', $this->chat->id)
            ->where('ticket_id', $ticket->id)
            ->get();

        foreach ($messages as $message) {
            $this->chat->deleteMessage($message->message_id)->send();
            $message->delete();
        }
    }

}
