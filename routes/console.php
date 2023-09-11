<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
            'template' => 'order.geo',
            'step_id' => 1
        ],

        'second' => [
            'template' => 'order.address',
            'step_id' => 2
        ],

        'third' => [
            'template' => 'order.contact',
            'step_id' => 3
        ],

        'fourth' => [
            'template' => 'order.whatsapp',
            'step_id' => 4
        ],

        'steps_amount' => 5
    ];

    Storage::put('first_scenario', json_encode($first_scenario));
});

Artisan::command('whatsapp', function () {
    $instance = [
        'id' => 1101856990,
        'token' => '20127fa42d194b0ea6b85a57a1672f9c6ff5afc796084809a5'
    ];

    $request_url = "https://api.green-api.com/waInstance{$instance['id']}/checkWhatsapp/{$instance['token']}";

    $response = Http::post($request_url, ['phoneNumber' => 19528854940]);

    Log::debug($response->body());
});
