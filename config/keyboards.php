<?php
return [
    'start' => [
        'template_name' => 'start',
        'buttons' => [
            'row' => [
                [
                    'type' => 'action',
                    'method' => 'start',
                    'param' => ['choice', '1'],
                    'language' => ['ru' => 'Начать', 'en' => 'Start']
                ],

                [
                    'type' => 'url',
                    'link' => 'https://t.me/+zZMk776R0oA0YWEy',
                    'language' => ['ru' => 'Отзывы', 'en' => 'Reviews']
                ]
            ]
        ]
    ],

    'accept_order' => [
        'template_name' => 'order.accept',
        'buttons' => [
            [
                'type' => 'action',
                'method' => 'first_scenario',
                'param' => ['choice', '1'],
                'language' => ['ru' => 'Заказать стирку', 'en' => 'Order Laundry']
            ]
        ]
    ],

    'order_accepted' => [
        'template_name' => 'order.accepted',
        'buttons' => [
            [
                'type' => 'action',
                'method' => 'accept_order',
                'param' => ['choice', '1'],
                'language' => ['ru' => 'Особые пожелания по стирке', 'en' => 'Laundry Special Requests']
            ],

            [
                'type' => 'action',
                'method' => 'cancel_order_accepted',
                'param' => ['choice', '2'],
                'language' => ['ru' => 'Отменить заказ', 'en' => 'Cancel order']
            ],

            [
                'type' => 'action',
                'method' => 'recommend_friends',
                'param' => ['choice', '3'],
                'language' => ['ru' => 'Рекомендовать друзьям', 'en' => 'Recommend to friends']
            ]
        ]
    ]
];
