<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];
    protected $with = ['user', 'chats'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statuses() {
        return $this->belongsToMany(
            Status::class,
            'order_status',
            'order_id',
            'status_id'
        );
    }

    public function chats() {
        return $this->belongsToMany(
            Chat::class,
            'chat_order',
            'order_id',
            'telegraph_chat_id'
        )->withPivot('message_id');
    }
}
