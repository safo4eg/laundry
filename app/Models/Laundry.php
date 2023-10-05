<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laundry extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'laundries';
    protected $guarded = [];
    protected $with = ['chats'];

    public function chats()
    {
        return $this->hasMany(Chat::class, 'laundry_id', 'id');
    }
}
