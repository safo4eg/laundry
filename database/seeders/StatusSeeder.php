<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('statuses')->insert([
            [
                'name' => 'created',
                'title' => 'User created the order'
            ],

            [
                'name' => 'confirmed',
                'title' => 'User confirmed an order'
            ],

            [
                'name' => 'sent_to_courier',
                'title' => 'Admin identified couriers'
            ],

            [
                'name' => 'canceled',
                'title' => 'User canceled an order'
            ],
        ]);
    }
}
