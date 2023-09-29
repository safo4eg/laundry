<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Referral extends Pivot
{
    public $timestamps = false;
    protected $table = 'referrals';
    protected $guarded = [];
}
