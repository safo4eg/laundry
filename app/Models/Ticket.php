<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function ticketItems(){
        return $this->hasMany(TicketItem::class);
    }
}
