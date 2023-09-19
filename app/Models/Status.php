<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'statuses';
    protected $guarded = [];

    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'order_status',
            'status_id',
            'order_id'
        );
    }
}
