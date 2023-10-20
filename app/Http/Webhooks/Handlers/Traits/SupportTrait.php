<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\ChatOrder;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
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
                if ($ticket->status_id !== 3) {
                    $ticket->update([
                        'status_id' => 3
                    ]);
                } else {
                    $this->send_ticket_card($this->chat, $ticket);
                }
                $this->delete_message_by_types([12]);

                $user = $ticket->user;
                $view = view('bot.support.send_user_answer', [
                    'text' => $ticket_item->text,
                    'ticket_id' => $ticket_id
                ]);
                // TODO: Доделать вид карточки для юзера + кнопки
                $keyboard = Keyboard::make()->buttons([
                    Button::make('У меня остались еще вопросы')
                        ->action('add_ticket')
                        ->param('ticket_id', $ticket->id),
                    Button::make('Вопросов нет')
                        ->action('close_ticket')
                        ->param('ticket_id', $ticket->id)
                ]);
                $this->send_message_to_user($user->chat_id, $view, $keyboard);
            } elseif ($flag == 2) {
                $this->delete_message_by_types([10, 11, 12]);
                $this->answer();
            }
        } else {
            $response = $this->chat->message(view('bot.support.confirm_ticket', [
                'ticket_id' => $ticket->id,
                'text' => $text
            ]))->keyboard(Keyboard::make()->buttons([
                Button::make('Yes')->action('confirm_answer')
                    ->param('ticket_id', $ticket->id)
                    ->param('choice', 1)->width(0.5),
                Button::make('No')->action('confirm_answer')
                    ->param('ticket_id', $ticket->id)
                    ->param('choice', 2)->width(0.5),
                Button::make('Cancel')->action('cancel')
            ]))->send();

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
        $this->delete_ticket_card($chat, $ticket);

        $view = view('bot.support.ticket_card', [
            'ticket' => $ticket,
            'baseUrl' => url()
        ]);

        $buttons = [
            Button::make('Answer')
                ->action('answer')
                ->param('ticket_id', $ticket->id)
                ->width(0.5),
            Button::make('Reject')
                ->action('reject')
                ->param('ticket_id', $ticket->id)
                ->width(0.5)
        ];

        if ($ticket->status_id == 3) {
            $buttons[] = Button::make('Close')
                ->action('close')
                ->param('ticket_id', $ticket->id)
                ->width(0.5);
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

    public function delete_ticket_card($chat, Ticket $ticket): void
    {
        $messages = ChatOrder::where('telegraph_chat_id', $chat->id)
            ->where('ticket_id', $ticket->id)
            ->get();

        foreach ($messages as $message) {
            $chat->deleteMessage($message->message_id)->send();
            $message->delete();
        }
    }
}
