<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketRejectReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ticket_reject_reasons')->insert([
            [
                'title' => 'inappropriate_content',
                'en_desc' => 'Inappropriate content',
                'ru_desc' => 'Неприемлемое содержание'
            ],

            [
                'title' => 'Insults',
                'en_desc' => 'Insults',
                'ru_desc' => 'Оскорбления'
            ],

            [
                'title' => 'other',
                'en_desc' => 'Other reason',
                'ru_desc' => 'Иная причина'
            ]
        ]);
    }
}
