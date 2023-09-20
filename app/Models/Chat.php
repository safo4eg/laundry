<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use DefStudio\Telegraph\Models\TelegraphChat;

class Chat extends TelegraphChat
{
    public $timestamps = false;
    protected $table = 'telegraph_chats';

    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'chat_order',
            'telegraph_chat_id',
            'order_id');
    }
}
