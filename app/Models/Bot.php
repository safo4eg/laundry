<?php

namespace App\Models;

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use DefStudio\Telegraph\Models\TelegraphChat;

class Bot extends TelegraphBot
{
    public $timestamps = false;
    protected $table = 'telegraph_bots';
}
