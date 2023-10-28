<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
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
                'signature_photo' => null,
                'en_desc' => 'User created the order',
                'ru_desc' => 'Пользователь создал заказ'
            ],

            [
                'name' => 'confirmed',
                'signature_photo' => null,
                'en_desc' => 'User confirmed an order',
                'ru_desc' => 'Пользователь подтвердил заказ'
            ],

            [
                'name' => 'sent_to_courier',
                'signature_photo' => null,
                'en_desc' => 'Sent to courier',
                'ru_desc' => 'Отправлен курьеру'
            ],

            [
                'name' => 'canceled',
                'signature_photo' => null,
                'en_desc' => 'User canceled an order',
                'ru_desc' => 'Пользователь отменил заказ'
            ],

            [
                'name' => 'courier_picked_order',
                'signature_photo' => 'Pickup photo',
                'en_desc' => 'Courier collected the clients items',
                'ru_desc' => 'Курьер забрал вещи у клиента'
            ],

            [
                'name' => 'deliver_in_laundry',
                'signature_photo' => 'Photo in laundry',
                'en_desc' => 'Courier delivered things to the laundry',
                'ru_desc' => 'Курьер доставил вещи в прачечную'
            ],

            [
                'name' => 'washer_picked_order',
                'signature_photo' => 'Photo before washing',
                'en_desc' => 'Washer picked the order',
                'ru_desc' => 'Прачка получила вещи'
            ],

            [
                'name' => 'things_are_washed',
                'signature_photo' => 'Photo after washing',
                'en_desc' => 'Things are washes',
                'ru_desc' => 'Вещи постираны'
            ],

            [
                'name' => 'send_for_weighing',
                'signature_photo' => null,
                'en_desc' => 'Sent to the courier for weighing',
                'ru_desc' => 'Вещи отправлены курьеру на взвешивание'
            ],

            [
                'name' => 'things_are_weighed',
                'signature_photo' => null,
                'en_desc' => 'The courier weighed the things',
                'ru_desc' => 'Курьер взвешал вещи'
            ],

            [
                'name' => 'ready_to_delivering',
                'signature_photo' => 'Photo on the scales',
                'en_desc' => 'Things are ready for delivery',
                'ru_desc' => 'Вещи готовы к отправке'
            ],

            [
                'name' => 'courier_on_the_way',
                'signature_photo' => null,
                'en_desc' => 'Courier on the way',
                'ru_desc' => 'Курьер доставляет готовый заказ'
            ],

            [
                'name' => 'delivered_to_client',
                'signature_photo' => 'Photo after delivery',
                'en_desc' => 'The courier delivered the items to the client',
                'ru_desc' => 'Курьер доставил вещи клиенту'
            ],

            [
                'name' => 'courier_received_payment',
                'signature_photo' => "💵Photo of money",
                'en_desc' => 'Courier received payment',
                'ru_desc' => 'Курьер получил оплату'
            ],
        ]);
    }
}
