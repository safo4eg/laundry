<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelegraphBotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('telegraph_bots')->insert([
            [
                'bot_id' => 5999454872,
                'token' => '5999454872:AAG-APM61vU2oIFqQ1D1VKYrXTXmvz4glFM',
                'first_name' => 'telegraph',
                'username' => 'rastan_telegraph_bot'
            ],
        ]);
    }
}
