<?php

namespace Database\Seeders;

use App\Http\Webhooks\Handlers\Courier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ticket_statuses')->insert([
            [
                'name' => 'created',
                'desc' => 'Ticket created'
            ],

            [
                'name' => 'consideration',
                'desc' => 'Ticket under consideration'
            ],

            [
                'name' => 'closed',
                'desc' => 'Ticket closed'
            ]
        ]);
    }
}
