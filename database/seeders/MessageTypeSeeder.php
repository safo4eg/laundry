<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MessageTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('message_types')->insert([
            ['title' => 'main'],
            ['title' => 'wishes'],
            ['title' => 'notification'],
            ['title' => 'command'],
            ['title' => 'request_photo'],
            ['title' => 'confirm_photo'],
            ['title' => 'select_order'],
            ['title' => 'photo'],
            ['title' => 'weighing'],
            ['title' => 'confirming_weighing']
        ]);
    }
}
