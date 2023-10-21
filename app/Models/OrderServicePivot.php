<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderServicePivot extends Pivot
{
    public $timestamps = false;
    protected $guarded = [];
    protected $table = 'order_service';

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }
}
