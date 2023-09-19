<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderStatus extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];

    protected $with = ['order', 'status'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
