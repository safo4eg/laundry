<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'files';
    protected $guarded = [];

    public function status() // статус заказа
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id', 'id');
    }
}
