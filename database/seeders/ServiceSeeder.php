<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('services')->insert([
            [
                'title' => 'Clothes',
                'price' => 80000,
                'request_text' => 'Enter clothing weight'
            ],

            [
                'title' => 'A pair of shoes',
                'price' => 120000,
                'request_text' => 'Enter the number of pairs of shoes'
            ],

            [
                'title' => 'Bed linen or towels',
                'price' => 50000,
                'request_text' => 'Enter the weight of bed linen and/or towels'
            ],

            [
                'title' => 'Organic wash',
                'price' => 120000,
                'request_text' => 'Enter weight Organic wash'
            ],
        ]);
    }
}
