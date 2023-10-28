<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('payment_methods')->insert([
            [
                'ru_desc' => 'Оплатить курьеру',
                'en_desc' => 'Pay the courier',
            ],

            [
                'ru_desc' => 'Перевод на BRI в рупиях',
                'en_desc' => 'Transfer to BRI in rupees',
            ],

            [
                'ru_desc' => 'Перевод на Тинькофф в рублях',
                'en_desc' => 'Transfer to Tinkoff in rubles',
            ],

            [
                'ru_desc' => 'Оплатить бонусами',
                'en_desc' => 'Pay with bonuses',
            ],
        ]);
    }
}
