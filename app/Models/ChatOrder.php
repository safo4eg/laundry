<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChatOrder extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'chat_order';
}
