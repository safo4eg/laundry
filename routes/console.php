<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Chat;
use App\Models\User;
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

Artisan::command('fake:user', function () {
    User::create([
        'username' => 'fake_user',
        'chat_id' => 123321
    ]);
});

Artisan::command('commands:register:bot=1', function () {
    $bot = TelegraphBot::where('id', 1)->first();

    $bot->registerCommands([
        '/start' => 'Order laundry',
        '/about' => 'About as',
        '/orders' => 'Show orders',
        '/profile' => 'Show profile',
        '/referrals' => 'Show referrals information'
    ])
        ->send();
});


