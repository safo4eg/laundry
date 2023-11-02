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
            ['title' => 'request_amount'],
            ['title' => 'confirm_weighing'],
            ['title' => 'text'],
            ['title' => 'dialogue'],
            ['title' => 'request_dialogue_message'],
            ['title' => 'message_from_client'],
            ['title' => 'admin_commands'],
            ['title' => 'admin_notification'],
            ['title' => 'admin_notification_request_text_ru'],
            ['title' => 'admin_notification_request_text_en'],
            ['title' => 'admin_notification_preview'],
            ['title' => 'admin_bonuses_id'], // запрос ИД пользователя
            ['title' => 'admin_bonuses_info'], // баланс пользователя
            ['title' => 'admin_bonuses_plus'], // запрос добавления
            ['title' => 'admin_bonuses_minus'], // запрос списания
            ['title' => 'admin_delete_order'], // запрос списания
            ['title' => 'ticket'],
            ['title' => 'request_text'],
            ['title' => 'text'],
            ['title' => 'confirm_ticket'],
            ['title' => 'select_ticket'],
            ['title' => 'ticket_reject'],
        ]);
    }
}
