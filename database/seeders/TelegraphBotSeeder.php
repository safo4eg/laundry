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
                'token' => '5999454872:AAG-APM61vU2oIFqQ1D1VKYrXTXmvz4glFM',
                'name' => 'telegraph'
            ],
        ]);
    }
}
