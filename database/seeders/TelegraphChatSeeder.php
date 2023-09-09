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
               'chat_id' => -908359373,
               'name' => 'Courier',
               'telegraph_bot_id' => 1
           ],
            [
               'chat_id' => -940706634,
               'name' => 'Washer',
               'telegraph_bot_id' => 1
           ]
        ]);
    }
}
