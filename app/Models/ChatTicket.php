<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatTicket extends Pivot
{
    use HasFactory;

    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'chat_ticket';
    protected $primaryKey = 'message_id';
    protected $with = ['chat'];

    public function chat(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Chat::class, 'telegraph_chat_id', 'id');
    }

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'ticket_id', 'id');
    }
}
