<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatuses extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('order_statuses')->insert([
            [
                'name' => 'created',
                'en_desc' => 'User created the order',
                'ru_desc' => 'Пользователь создал заказ'
            ],

            [
                'name' => 'confirmed',
                'en_desc' => 'User confirmed an order',
                'ru_desc' => 'Пользователь подтвердил заказ'
            ],

            [
                'name' => 'sent_to_courier',
                'en_desc' => 'Sent to courier',
                'ru_desc' => 'Отправлен курьеру'
            ],

            [
                'name' => 'canceled',
                'en_desc' => 'User canceled an order',
                'ru_desc' => 'Пользователь отменил заказ'
            ],

            [
                'name' => 'picked',
                'en_desc' => 'Courier collected the clients items',
                'ru_desc' => 'Курьер забрал вещи у клиента'
            ]
        ]);
    }
}
