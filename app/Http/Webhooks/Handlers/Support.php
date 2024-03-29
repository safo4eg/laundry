<?php


namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Http\Webhooks\Handlers\Traits\SupportTrait;
use App\Http\Webhooks\Handlers\Traits\SupportUserTrait;
use App\Http\Webhooks\Handlers\Traits\UserCommandsFuncsTrait;
use App\Http\Webhooks\Handlers\Traits\UserMessageTrait;
use App\Models\ChatOrderPivot;
use App\Models\Ticket;
use App\Models\TicketRejectReason;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;

class Support extends WebhookHandler
{
    public function __construct()
    {
        $this->template_prefix = 'bot.support';
        parent::__construct();
    }

    public function answer(): void
    {
        $view = "$this->template_prefix.enter_answer";
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();

        $this->delete_message_by_types([27]);
        $response = $this->chat->message(view($view, [
            'ticket' => $ticket
        ]))->keyboard(Keyboard::make()->buttons([
            Button::make('Cancel')
                ->action('delete_message_by_types')
                ->param('delete', 1)
                ->param('type_id', 10)
        ]))->send();

        ChatOrderPivot::create([
            'telegraph_chat_id' => $this->chat->id,
            'message_id' => $response->telegraphMessageId(),
            'message_type_id' => 27,
            'ticket_id' => $ticket->id
        ]);
    }

    use UserMessageTrait;

    public function reject(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();

        $reason_flag = $this->data->get('reason_id');
        if (isset($reason_flag)) {
            $user = UserModel::where('id', $ticket->user_id)->first();
            $view = view("bot.user.$user->language_code.support.reject_ticket_notification", [
                'reason' => TicketRejectReason::where('id', $reason_flag)->first()
            ]);
            $this->send_message_to_user($user, $view);

            $ticket->update([
                'status_id' => 5
            ]);

            $this->delete_message_by_types([26, 27, 28, 29, 30, 31]);

        } else {
            $buttons = [];
            $reasons = TicketRejectReason::all();
            foreach ($reasons as $reason) {
                $buttons[] = Button::make($reason->en_desc)->action('reject')
                    ->param('reason_id', $reason->id)
                    ->param('ticket_id', $ticket->id);
            }

            $ticket_card = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('ticket_id', $ticket->id)->where('message_type_id', 26)
                ->first();

            $view = "$this->template_prefix.reject_ticket";
            $response = $this->chat->reply($ticket_card->message_id)->message(view($view, [
                'ticket' => $ticket
            ]))->keyboard(Keyboard::make()
                ->buttons($buttons))
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 31,
                'ticket_id' => $ticket->id
            ]);
        }
    }


    use SupportTrait;
    use ChatsHelperTrait;

    public function close(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();
        $ticket->update([
            'status_id' => 4
        ]);

        $this->delete_ticket_card($this->chat, $ticket);
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $text = $this->message->text();

        if ($text) {
            $this->chat->storage()->set('text', $text);

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'message_id' => $this->messageId,
                'ticket_id' => null,
                'message_type_id' => 28
            ]);

            $chat_ticket = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 27)
                ->first();

            $ticket = $chat_ticket?->ticket;
            if (isset($chat_ticket)) {
                $this->delete_message_by_types([27, 28]);
                $this->confirm_answer($text, $ticket);
            }
        }
    }
}
