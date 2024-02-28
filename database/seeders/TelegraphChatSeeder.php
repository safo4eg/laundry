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
                'chat_id' => -1002124802870,
                'name' => 'Manager',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ],

            [
                'chat_id' => -1002102319735,
                'name' => 'Courier',
                'telegraph_bot_id' => 1,
                'laundry_id' => 2
            ],

            [
                'chat_id' => -1002026302520,
                'name' => 'Courier',
                'telegraph_bot_id' => 1,
                'laundry_id' => 1
            ],

            [
                'chat_id' => -1001998228351,
                'name' => 'Washer',
                'telegraph_bot_id' => 1,
                'laundry_id' => 2
            ],

            [
                'chat_id' => -1002105790767,
                'name' => 'Washer',
                'telegraph_bot_id' => 1,
                'laundry_id' => 1
            ],

            [
                'chat_id' => -1002100323031,
                'name' => 'Admin',
                'telegraph_bot_id' => 1,
                'laundry_id' => 1
            ],

            [
                'chat_id' => -1002043605449,
                'name' => 'Support',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ],

            [
                'chat_id' => -1002091927252,
                'name' => 'Archive',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ]
        ]);
    }
}
