<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderStatusPivot extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];

    protected $with = ['order', 'status'];
    protected $table = 'order_status';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function status()
    {
        return $this->belongsTo(OrderStatus::class);
    }
}
