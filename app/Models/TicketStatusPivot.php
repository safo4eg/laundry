<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TicketStatusPivot extends Pivot
{

    public $timestamps = false;
    protected $guarded = [];

    protected $with = ['ticket', 'status'];
    protected $table = 'ticket_status';

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function status()
    {
        return $this->belongsTo(TicketStatus::class);
    }
}
