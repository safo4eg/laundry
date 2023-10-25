<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use DefStudio\Telegraph\Contracts\Storable;
use DefStudio\Telegraph\Contracts\StorageDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public $timestamps = false;

    protected $guarded = [];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'inviter_id', 'id');
    }

    public function ticket()
    {
        return $this->hasMany(Ticket::class);
    }

    public function getCurrentOrder(): Order
    {
        return $this->orders()->where('status_id', '<', 6)->first();
    }

    public function getActiveOrderAttribute(): Order|null
    {
        return $this->orders->where('active', 1)->first();
    }

    public function getIncompleteUserTickets()
    {
        return $this->ticket->where('status_id', 1)->all();
    }

    public function getCurrentUserTickets()
    {
        return $this->ticket->whereIn('status_id', [2, 3])->all();
    }

    public function getClosedUserTickets()
    {
        return $this->ticket->whereIn('status_id', [4, 5])->all();
    }
}
