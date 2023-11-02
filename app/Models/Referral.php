<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Referral extends Pivot
{
    public $timestamps = false;
    protected $table = 'referrals';
    protected $guarded = [];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id', 'id');
    }
}
