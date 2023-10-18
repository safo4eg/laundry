<?php


namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\UserMessageTrait;
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
        $this->config = config('buttons.support');
        $this->template_prefix = 'bot.support';
        parent::__construct();
    }

    public function answer(): void
    {
        $view = "$this->template_prefix.enter_answer";
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();

        $this->chat->message(view($view, [
            'ticket' => $ticket
        ]))->keyboard(Keyboard::make()->buttons([
            Button::make('Отмена')->action('cancel')
        ]))->send();
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
                'status_id' => 4
            ]);
            $this->chat->deleteMessage($this->messageId)->send();
        } else {
            $buttons = [];
            $reasons = TicketRejectReason::all();
            foreach ($reasons as $reason) {
                $buttons[] = Button::make($reason->en_desc)->action('reject')
                    ->param('reason_id', $reason->id)
                    ->param('ticket_id', $ticket->id);
            }

            $view = "$this->template_prefix.reject_ticket";
            $this->chat->message(view($view, [
                'ticket' => $ticket
            ]))->keyboard(Keyboard::make()
                ->buttons($buttons))
                ->send();
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $text = $this->message->text();
    }
}
