<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatOrderPivot extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'chat_order';
    protected $primaryKey = 'message_id';
    protected $with = ['chat'];

    public function chat()
    {
        return $this->belongsTo(Chat::class, 'telegraph_chat_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
