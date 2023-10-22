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
                'name' => 'courier_picked_order',
                'en_desc' => 'Courier collected the clients items',
                'ru_desc' => 'Курьер забрал вещи у клиента'
            ],

            [
                'name' => 'deliver_in_laundry',
                'en_desc' => 'Courier delivered things to the laundry',
                'ru_desc' => 'Курьер доставил вещи в прачечную'
            ],

            [
                'name' => 'washer_picked_order',
                'en_desc' => 'Washer picked the order',
                'ru_desc' => 'Прачка получила вещи'
            ],

            [
                'name' => 'things_are_washed',
                'en_desc' => 'Things are washes',
                'ru_desc' => 'Вещи постираны'
            ],

            [
                'name' => 'send_for_weighing',
                'en_desc' => 'Sent to the courier for weighing',
                'ru_desc' => 'Вещи отправлены курьеру на взвешивание'
            ],

            [
                'name' => 'things_are_weighed',
                'en_desc' => 'The courier weighed the things',
                'ru_desc' => 'Курьер взвешал вещи'
            ],

            [
                'name' => 'ready_to_delivering',
                'en_desc' => 'Things are ready for delivery',
                'ru_desc' => 'Вещи готовы к отправке'
            ],

            [
                'name' => 'delivered_to_client',
                'en_desc' => 'The courier delivered the items to the client',
                'ru_desc' => 'Курьер доставил вещи клиенту'
            ],
        ]);
    }
}
