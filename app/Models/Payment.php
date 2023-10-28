<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $guarded = [];

    public function method()
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
