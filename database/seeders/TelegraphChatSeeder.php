<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TelegraphChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('telegraph_chats')->insert([

            [
                'chat_id' => -4005568217,
                'name' => 'Manager',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ],

            [
                'chat_id' => -4011926691,
                'name' => 'Courier',
                'telegraph_bot_id' => 1,
                'laundry_id' => 2
            ],

            [
                'chat_id' => -4053308523,
                'name' => 'Courier',
                'telegraph_bot_id' => 1,
                'laundry_id' => 1
            ],

            [
                'chat_id' => -1001975491068,
                'name' => 'Support',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ],

            [
                'chat_id' => -1001964319343,
                'name' => 'Archive',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ]
        ]);
    }
}
