<?php

namespace App\Observers;

use App\Models\OrderStatusPivot;
use App\Models\Ticket;
use App\Models\TicketStatusPivot;
use Carbon\Carbon;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        TicketStatusPivot::create([
            'ticket_id' => $ticket->id,
            'status_id' => $ticket->status_id,
            'created_at' => Carbon::now()
        ]);
    }

    public function updated(Ticket $ticket): void
    {
        $attributes = $ticket->getDirty();

        if(isset($attributes['status_id'])) {
            TicketStatusPivot::create([
                'ticket_id' => $ticket->id,
                'status_id' => $ticket->status_id,
                'created_at' => Carbon::now()
            ]);
        }
    }
}
