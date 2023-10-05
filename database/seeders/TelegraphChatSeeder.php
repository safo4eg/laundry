<?php

namespace Database\Seeders;

use App\Http\Webhooks\Handlers\Courier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'chat_id' => -4099543781,
                'name' => 'Manager',
                'telegraph_bot_id' => 1,
                'laundry_id' => null
            ],

            [
                'chat_id' => -4061588577,
                'name' => 'CangguCouriers',
                'telegraph_bot_id' => 1,
                'laundry_id' => 2
            ],

            [
                'chat_id' => -4025423918,
                'name' => 'SanurCouriers',
                'telegraph_bot_id' => 1,
                'laundry_id' => 1
            ]

        ]);
    }
}
