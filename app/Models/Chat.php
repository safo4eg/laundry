<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use DefStudio\Telegraph\Models\TelegraphChat;

class Chat extends TelegraphChat
{
    public $timestamps = false;
    protected $table = 'telegraph_chats';
}
