<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Chat;
use DefStudio\Telegraph\Models\TelegraphBot;
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

Artisan::command('scenarios', function () {
    $scenarios = [
        'first' => [
            1 => ['template' => '.order.geo', 'step_id' => 1],
            2 => ['template' => '.order.address', 'step_id' => 2],
            3 => ['template' => '.order.contact', 'step_id' => 3],
            4 => ['template' => '.order.whatsapp', 'step_id' => 4],
            5 => ['template' => '.order.accept', 'step_id' => 5],
        ],

        'second' => [
            1 => ['template' => '.order.geo', 'step_id' => 1],
            2 => ['template' => '.order.address', 'step_id' => 2],
            3 => ['template' => '.order.accept', 'step_id' => 3]
        ]
    ];

    Storage::put('scenarios', json_encode($scenarios));
});

Artisan::command('commands:register:bot=1', function () {
    $bot = TelegraphBot::where('id', 1)->first();

    $bot->registerCommands([
        '/start' => 'Order laundry',
        '/about' => 'About as',
        '/orders' => 'Show orders'
    ])
        ->send();
});
