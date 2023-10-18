<?php

namespace App\Http\Webhooks\Handlers;

use App\Models\Chat;
use App\Models\Ticket;
use DefStudio\Telegraph\Handlers\WebhookHandler;

class Archive extends WebhookHandler
{
    public function return_ticket(): void
    {
        $ticket = Ticket::where('id', $this->data->get('ticket_id'))->first();
        $support_chat_id = Chat::where('name', 'Support')->first();

        if ($ticket->ticketItems->search($support_chat_id)) {
            $ticket->update([
                'status_id' => 3
            ]);
        } else {
            $ticket->update([
                'status_id' => 2
            ]);
        }

        $this->chat->deleteMessage($this->messageId)->send();
    }
}
