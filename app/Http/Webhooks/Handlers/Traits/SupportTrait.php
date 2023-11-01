<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Http\Webhooks\Handlers\User;
use App\Models\Chat;
use App\Models\ChatOrder;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Services\FakeRequest;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;


trait SupportTrait
{
    // TODO: Отправка через FakeRequest
    public function send_user_answer(TicketItem $ticket_item): void
    {
        $ticket_id = $this->data->get('ticket_id');
        $ticket = Ticket::where('id', $ticket_id)->first();
        $user = $ticket->user;

        $chat = Chat::factory()->make([
            'chat_id' => $user->chat_id,
            'name' => 'Temp',
            'telegraph_bot_id' => 1
        ]);

        $fake_dataset = [
            'action' => 'get_support_answer',
            'params' => [
                'ticket_item_id' => $ticket_item->id
            ]
        ];

        $fake_request = FakeRequest::callback_query($chat, $this->bot, $fake_dataset);
        (new User($ticket->user))->handle($fake_request, $this->bot);
    }

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
                $this->send_user_answer($ticket_item);
            } elseif ($flag == 2) {
                $this->delete_message_by_types([10, 11, 12]);
                $this->answer();
            }
        } else {
            $this->delete_message_by_types([10, 11, 12]);
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

    public function send_ticket_card($chat, Ticket $ticket): void
    {
        $this->delete_ticket_card($chat, $ticket);
        $last_order = Order::where('user_id', $ticket->user->id)->orderByDesc('id')->first();
        $view = $this->prepare_template('bot.support.ticket_card', [
            'ticket' => $ticket,
            'baseUrl' => url(),
            'user' => $ticket->user,
            'last_order' => $last_order
        ]);

        $response = $chat->message($view)
            ->keyboard($this->update_ticket_keyboard_by_status($ticket))
            ->send();

        ChatOrder::create([
            'telegraph_chat_id' => $chat->id,
            'message_id' => $response->telegraphMessageId(),
            'ticket_id' => $ticket->id,
            'message_type_id' => 9
        ]);
    }

    public function update_ticket_keyboard_by_status(Ticket $ticket): Keyboard
    {
        $buttons = [];

        switch ($ticket->status_id) {
            case 2:
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
                break;
            case 3:
                $buttons = [
                    Button::make('Answer')
                        ->action('answer')
                        ->param('ticket_id', $ticket->id)
                        ->width(0.5),
                    Button::make('Reject')
                        ->action('reject')
                        ->param('ticket_id', $ticket->id)
                        ->width(0.5),
                    $buttons[] = Button::make('Close')
                        ->action('close')
                        ->param('ticket_id', $ticket->id)
                ];
                break;
            case 4:
            case 5:
                $buttons = [
                    Button::make('Return')
                        ->action('return_ticket')
                        ->param('ticket_id', $ticket->id)
                        ->width(0.5),
                ];
        }

        return Keyboard::make()->buttons($buttons);
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
