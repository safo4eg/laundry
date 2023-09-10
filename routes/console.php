<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test', function () {
    $first_scenario = [
        'first' => [
            'template' => 'order.geo'
        ],

        'second' => [
            'template' => 'order.address'
        ],

        'third' => [
            'template' => 'order.contact'
        ],

        'fourth' => [
            'template' => 'order.whatsapp'
        ],

        'steps_amount' => 4
    ];

    Storage::put('first_scenario', json_encode($first_scenario));
});
