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
                'bot_id' => 7084484101,
                'token' => '7084484101:AAEEcM58bZDUP7t72MZmKwoLEtkQ9Y1WD60',
                'first_name' => 'telegraph',
                'username' => 'rastan_telegraph_bot'
            ],
        ]);
    }
}
