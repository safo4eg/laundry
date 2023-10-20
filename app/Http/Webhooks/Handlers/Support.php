<?php


namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Http\Webhooks\Handlers\Traits\SupportTrait;
use App\Http\Webhooks\Handlers\Traits\UserMessageTrait;
use App\Models\ChatOrder;
use App\Models\Ticket;
use App\Models\TicketRejectReason;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;

class Support extends WebhookHandler
{
    public function __construct()
    {
        $this->config = config('buttons.support');
        $this->template_prefix = 'bot.support';
        parent::__construct();
    }

    public function answer(): void
    {
        $view = "$this->template_prefix.enter_answer";
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();

        $response = $this->chat->message(view($view, [
            'ticket' => $ticket
        ]))->keyboard(Keyboard::make()->buttons([
            Button::make('Отмена')->action('cancel')
        ]))->send();

        ChatOrder::create([
            'telegraph_chat_id' => $this->chat->id,
            'message_id' => $response->telegraphMessageId(),
            'message_type_id' => 10,
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
            $this->send_message_to_user($user->chat_id, $view);

            $ticket->update([
                'status_id' => 5
            ]);

            $this->delete_message_by_types([9, 10, 11, 12, 13, 14, 15]);

        } else {
            $buttons = [];
            $reasons = TicketRejectReason::all();
            foreach ($reasons as $reason) {
                $buttons[] = Button::make($reason->en_desc)->action('reject')
                    ->param('reason_id', $reason->id)
                    ->param('ticket_id', $ticket->id);
            }

            $view = "$this->template_prefix.reject_ticket";
            $response = $this->chat->message(view($view, [
                'ticket' => $ticket
            ]))->keyboard(Keyboard::make()
                ->buttons($buttons))
                ->send();

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 15,
                'ticket_id' => $ticket->id
            ]);
        }
    }

    public function close(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'));
        $ticket->update([
           'status_id' => 4
        ]);

        $this->delete_message_by_types();
    }

    use SupportTrait;
    use ChatsHelperTrait;

    protected function handleChatMessage(Stringable $text): void
    {
        $text = $this->message->text();

        if ($text) {
            $this->chat->storage()->set('text', $text);

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'message_id' => $this->messageId,
                'ticket_id' => null,
                'message_type_id' => 11
            ]);

            $chat_ticket = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 10)
                ->first();

            $ticket = $chat_ticket?->ticket;
            if (isset($chat_ticket)) {
                $this->delete_message_by_types([11, 10]);
                $this->confirm_answer($text, $ticket);
            }
        }
    }
}
