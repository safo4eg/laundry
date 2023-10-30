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

        if ($ticket->status_id == 4) {
            $ticket->update([
                'status_id' => 3
            ]);
        } else if($ticket->status_id == 5) {
            $ticket->update([
                'status_id' => 2
            ]);
        }

        $this->chat->deleteMessage($this->messageId)->send();
    }
}
