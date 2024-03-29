<?php

namespace App\Models;

use Database\Factories\ChatFactory;
use DefStudio\Telegraph\Database\Factories\TelegraphChatFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
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

    public function laundry()
    {
        return $this->belongsTo(Laundry::class, 'laundry_id', 'id');
    }

    protected static function newFactory(): Factory
    {
        return ChatFactory::new();
    }
}
