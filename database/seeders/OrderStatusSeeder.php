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
                'en_desc' => '🤖Created',
                'ru_desc' => '🤖Заказ создан'
            ],

            [
                'name' => 'confirmed',
                'signature_photo' => null,
                'en_desc' => '☑️Confirmed',
                'ru_desc' => '☑️Заказ подтвержден'
            ],

            [
                'name' => 'sent_to_courier',
                'signature_photo' => null,
                'en_desc' => '👌🏻Sent to courier',
                'ru_desc' => '👌🏻Отправлен курьеру'
            ],

            [
                'name' => 'canceled',
                'signature_photo' => null,
                'en_desc' => '❌User canceled an order',
                'ru_desc' => '❌Пользователь отменил заказ'
            ],

            [
                'name' => 'courier_picked_order',
                'signature_photo' => 'Pickup photo',
                'en_desc' => '📥Courier picked up',
                'ru_desc' => '📥Курьер забрал вещи'
            ],

            [
                'name' => 'deliver_in_laundry',
                'signature_photo' => 'Photo in laundry',
                'en_desc' => '🗺Courier delivered to laundry',
                'ru_desc' => '🗺Курьер доставил вещи в прачечную'
            ],

            [
                'name' => 'washer_picked_order',
                'signature_photo' => 'Photo before washing',
                'en_desc' => '🧼Washing started',
                'ru_desc' => '🧼Стирка началась'
            ],

            [
                'name' => 'things_are_washed',
                'signature_photo' => 'Photo after washing',
                'en_desc' => '💪Washed',
                'ru_desc' => '💪Вещи постираны'
            ],

            [
                'name' => 'send_for_weighing',
                'signature_photo' => null,
                'en_desc' => '💨Sent to the courier for weighing',
                'ru_desc' => '💨Вещи отправлены курьеру на взвешивание'
            ],

            [
                'name' => 'things_are_weighed',
                'signature_photo' => null,
                'en_desc' => '🛒Weighed',
                'ru_desc' => '🛒Вещи взвешены'
            ],

            [
                'name' => 'ready_to_delivering',
                'signature_photo' => 'Photo on the scales',
                'en_desc' => '🔥Things are ready for delivery',
                'ru_desc' => '🔥Вещи готовы к доставке'
            ],

            [
                'name' => 'courier_on_the_way',
                'signature_photo' => null,
                'en_desc' => '🏂Courier on the way',
                'ru_desc' => '🏂Курьер доставляет заказ'
            ],

            [
                'name' => 'delivered_to_client',
                'signature_photo' => 'Photo after delivery',
                'en_desc' => '🛵Courier delivered',
                'ru_desc' => '🛵Заказ доставлен'
            ],

            [
                'name' => 'payment',
                'signature_photo' => "💵Payment",
                'en_desc' => '💵Order has been paid',
                'ru_desc' => '💵Заказ оплачен'
            ],
        ]);
    }
}
