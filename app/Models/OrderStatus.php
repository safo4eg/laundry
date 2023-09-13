<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderStatus extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];
}
