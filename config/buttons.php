<?php
return [
    'user' => [
        'start' => [
            'new_order' => ['ru' => 'Начать', 'en' => 'Start'],
            'continue_order' => ['ru' => 'Продолжить оформление заказа', 'en' => 'Continue ordering'],
            'reviews' => ['ru' => 'Отзывы', 'en' => 'Reviews']
        ],

        'select_language' => [
            'russia' => 'Русский',
            'english' => 'English',
            'back' => ['ru' => 'Вернуться назад', 'en' => 'Go to back']
        ],

        'send_location' => ['ru' => 'Отправить локацию', 'en' => 'Send location'],
        'send_contact' => ['ru' => 'Отправить номер', 'en' => 'Send number'],
        'accept_order' => ['ru' => 'Заказать стирку', 'en' => 'Order Laundry'],

        'order_accepted' => [
            'wishes' => ['ru' => 'Особые пожелания по стирке', 'en' => 'Laundry Special Requests'],
            'cancel' => ['ru' => 'Отменить заказ', 'en' => 'Cancel order'],
            'recommend' => ['ru' => 'Рекомендовать друзьям', 'en' => 'Recommend to friends']
        ],

        'order_wishes' => ['ru' => 'Назад', 'en' => 'Back'],

        'cancel_order' => [
            'check_bot' => ['ru' => 'Просто решил проверить Бот', 'en' => 'Just decided to check the Bot'],
            'changed_my_mind' => ['ru' => 'Передумал стирать', 'en' => 'Changed my mind'],
            'quality' => ['ru' => 'Переживаю за качество стирки', 'en' => 'Worried about the quality of the wash'],
            'expensive' => ['ru' => 'Дорого', 'en' => 'Expensive'],
            'back' => ['ru' => '⬅️ Вернуться в заказ', 'en' => '⬅️ Back to order']
        ],

        'order_canceled' => [
            'start' => ['ru' => 'Заказать стирку', 'en' => 'Order Laundry'],
            'recommend' => ['ru' => 'Рекомендовать друзьям', 'en' => 'Recommend to friends']
        ],

        'about_us' => [
            'new_order' => ['ru' => 'Заказать стирку', 'en' => 'Order laundry'],
            'continue_order' => ['ru' => 'Продолжить оформление заказа', 'en' => 'Continue ordering'],
        ],

        'orders' => [
            'new_order' => ['ru' => 'Заказать стирку', 'en' => 'Order laundry'],
            'continue_order' => ['ru' => 'Продолжить оформление заказа', 'en' => 'Continue ordering'],
            'active' => ['ru' => 'Активные', 'en' => 'Active'],
            'completed' => ['ru' => 'Завершенные', 'en' => 'Completed'],
            'back' => ['ru' => 'Вернуться назад', 'en' => 'Go to back'],
        ],

        'order_info' => [
            'wishes' => ['ru' => 'Особые пожелания по стирке', 'en' => 'Laundry Special Requests'],
            'cancel' => ['ru' => 'Отменить заказ', 'en' => 'Cancel order'],
            'recommend' => ['ru' => 'Рекомендовать друзьям', 'en' => 'Recommend to friends'],
            'back' => ['ru' => 'Вернуться назад', 'en' => 'Go to back'],
        ],

        'profile' => [
            'new_order' => ['ru' => 'Заказать стирку', 'en' => 'Order laundry'],
            'continue_order' => ['ru' => 'Продолжить оформление заказа', 'en' => 'Continue ordering'],
            'phone_number' => ['ru' => 'Изменить номер телефона', 'en' => 'Change phone number'],
            'whatsapp' => ['ru' => 'Изменить whatsapp', 'en' => 'Change whatsapp'],
            'language' => ['ru' => 'Сменить язык', 'en' => 'Change language'],
            'back' => ['ru' => 'Вернуться назад', 'en' => 'Go to back']
        ],

        'referrals' => [
            'new_order' => ['ru' => 'Заказать стирку', 'en' => 'Order laundry'],
            'continue_order' => ['ru' => 'Продолжить оформление заказа', 'en' => 'Continue ordering'],
            'recommend' => ['ru' => 'Рекомендовать другу', 'en' => 'Recommend to friends'],
            'info' => ['ru' => 'Мои рефералы', 'en' => 'My referrals'],
            'back' => ['ru' => 'Вернуться назад', 'en' => 'Go to back']
        ],
        // обернуть begin
        'support' => ['ru' => 'Написать', 'en' => 'Write'],
        'confirm' => [
            'yes' => ['ru' => 'Да', 'en' => 'Yes'],
            'no' => ['ru' => 'Нет', 'en' => 'No']
        ],
        'lc' => [
            'active' => ['ru' => 'Активные', 'en' => 'Active'],
            'archive' => ['ru' => 'Архивные', 'en' => 'Archive']
        ],
        'back' => ['ru' => 'Назад', 'en' => 'Back'],
        'tickets' => ['ru' => 'Проверить статус заявки', 'en' => 'Check application status' ]
        // end
    ],

    'manager' => [
        'send_to_couriers' => null, // подхватываются в нужных местах из таблицы laundries
    ],

    'courier' => [
        3 => 'Pickup',
        5 => 'In the Laundry',
        9 => 'Weigh', // отправлены на взвешивание
        10 => 'Photo on the scales', // курьер взвешал вещи
        11 => 'Photos of delivered things', // курьер сделал фото вещей (готовы к отправке)
        12 => 'Things have been delivered' // вещи доставлены
    ],

    'washer' => [
        6 => 'Photo before washing', // когда только доставили статус = Доставлен (ид=6) меняет на 7
        7 => 'Photo after washing', // вещи постираны статус = Постираны (ид=7) меняет на 8
        8 => 'Ready for weighing', // вещи переданы курьеру на взвешивание (ид=8) меняет на 9
    ],

    'chats' => [
        'request_photo' => [
            'cancel' => 'Cancel'
        ],

        'confirm_photo' => [
            'yes' => 'YES',
            'no' => 'NO',
            'cancel' => 'CANCEL'
        ],

        'select_order' => [
            'cancel' => 'Cancel'
        ],

        'report' => 'Report',

        'weighing' => [
            'accept' => 'Accept',
            'reset' => 'Reset',
            'cancel' => 'Cancel'
        ],

        'confirm_weighing' => [
            'yes' => '✅YES',
            'no' => '❌NO'
        ]
    ]
];
