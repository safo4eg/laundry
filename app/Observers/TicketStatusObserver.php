<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Traits\SupportTrait;
use App\Models\Chat;
use App\Models\TicketStatusPivot;


class TicketStatusObserver
{
    use SupportTrait;

    public function created(TicketStatusPivot $ticket_status): void
    {
        $ticket = $ticket_status->ticket;

        if ($ticket->status_id == 2) {
            $chat = Chat::where('name', 'Support')->first();
            $this->send_ticket_card($chat, $ticket);
        }
        if ($ticket->status_id === 3) {
            $chat = Chat::where('name', 'Support')->first();
            $this->send_ticket_card($chat, $ticket);
        }
        if ($ticket->status_id === 5 || $ticket->status_id === 4) {
            $chat = Chat::where('name', 'Archive')->first();
            $this->send_ticket_card($chat, $ticket);
        }
    }
}
