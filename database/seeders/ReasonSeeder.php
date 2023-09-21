<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('reasons')->insert([
            [
                'title' => 'check_bot',
                'en_desc' => 'Just decided to check the Bot',
                'ru_desc' => 'Просто решил проверить Бот'
            ],

            [
                'title' => 'changed_my_mind',
                'en_desc' => 'Changed my mind',
                'ru_desc' => 'Передумал стирать'
            ],

            [
                'title' => 'quality',
                'en_desc' => 'Worried about the quality of the wash',
                'ru_desc' => 'Переживаю за качество стирки'
            ],

            [
                'title' => 'expensive',
                'en_desc' => 'Expensive',
                'ru_desc' => 'Дорого'
            ]
        ]);
    }
}
