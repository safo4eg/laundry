<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatOrder extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'chat_order';
    protected $primaryKey = 'message_id';
    protected $with = ['chat'];

    public function chat(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Chat::class, 'telegraph_chat_id', 'id');
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }
}
