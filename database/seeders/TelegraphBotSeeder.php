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
                'bot_id' => 6152481327,
                'token' => '6152481327:AAG3VzvXBmKIanOoWqMM_e9_PqBISVa176Y',
                'first_name' => 'test',
                'username' => 'lann777_bot'
            ],
        ]);
    }
}
