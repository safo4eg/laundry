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
                'title' => 'clothes',
                'price' => 80000,
            ],

            [
                'title' => 'shoes',
                'price' => 120000,
            ],

            [
                'title' => 'bed_linen',
                'price' => 50000,
            ],

            [
                'title' => 'organic',
                'price' => 120000,
            ],
        ]);
    }
}
