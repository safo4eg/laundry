<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\FirstAndSecondScenarioTrait;
use App\Http\Webhooks\Handlers\Traits\UserCommandsFuncsTrait;
use App\Models\File;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderServicePivot;
use App\Models\OrderStatusPivot;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Referral;
use App\Models\User as UserModel;
use App\Services\FakeRequest;
use App\Services\Helper;
use App\Services\QR;
use Carbon\Carbon;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;


class User extends WebhookHandler
{
    use FirstAndSecondScenarioTrait, UserCommandsFuncsTrait;

    private UserModel|null $user = null;
    private array $config;
    private string $template_prefix;

    public function __construct(UserModel|null $user)
    {
        $this->config = config('buttons.user');
        $this->user = $user;
        $this->template_prefix = 'bot.user.';
        parent::__construct();
    }

    /* добавление бонусов пригласителю */
    /* своих кнопок для обрабобтка не будет */
    public function addition_balance(): void
    {
        $bonus = $this->data->get('bonus');

        $ref_link = "https://t.me/share/url?url=https://t.me/rastan_telegraph_bot?start=ref{$this->user->id}";
        $template = $this->template_prefix . $this->user->language_code . '.notifications.addition_bonuses';
        $template_data = [
            'ref_link' => $ref_link,
            'bonus' => $bonus,
            'balance' => $this->user->balance
        ];
        $buttons_text = $this->config['referrals']['recommend'][$this->user->language_code];
        $keyboard = Keyboard::make()->buttons([
            Button::make($buttons_text)->url($ref_link)
        ]);

        $this->terminate_active_page();

        $response = $this->chat
            ->message(Helper::prepare_template($template, $template_data))
            ->keyboard($keyboard)
            ->send();

        $this->user->update([
            'page' => 'addition_bonus',
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function payment_photo(): void
    {
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();
        $request = $this->data->get('request');
        $confirm = $this->data->get('confirm');

        /* запрос фото оплаты, может быть вызвано ток через кнопку! */
        if (isset($request)) {
            $photo = $this->data->get('photo');

            $template = $this->template_prefix . $this->user->language_code . '.order.request_payment_photo';
            $keyboard = Keyboard::make()->buttons([
                Button::make($this->config['back'][$this->user->language_code])
                    ->action('payment_page')
                    ->param('back', 1)
                    ->param('order_id', $order->id)
            ]);

            if (isset($photo)) {
                $this->chat->deleteMessage($this->user->message_id)->send();
                $response = $this->chat
                    ->message(Helper::prepare_template($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat
                    ->message(Helper::prepare_template($template, ['order' => $order]))
                    ->edit($this->user->message_id)
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'payment_photo',
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if (isset($confirm)) {
            $yes = $this->data->get('yes');
            $no = $this->data->get('no');

            $photo_id = $this->callbackQuery->from()->storage()->get('payment_photo_id');
            $photo_path = "User/{$this->user->id}/payments/{$order->payment->id}/{$photo_id}.jpg";

            if (isset($yes) or isset($no)) {
                if (isset($yes)) {
                    File::create([
                        'order_id' => $order->id,
                        'file_type_id' => 1,
                        'path' => $photo_path
                    ]);

                    $order->payment->update(['status_id' => 2]);

                    $fake_data = [
                        'action' => 'payment_page',
                        'params' => [
                            'order_id' => $order->id,
                        ]
                    ];
                }

                if (isset($no)) {
                    Storage::delete($photo_path);
                    $fake_data = [
                        'action' => 'payment_photo',
                        'params' => [
                            'request' => 1,
                            'order_id' => $order->id,
                            'photo' => 1
                        ]
                    ];
                }

                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_data);
                (new self($this->user))->handle($fake_request, $this->bot);
            }

            if (!isset($yes) and !isset($no)) {
                $template = $this->template_prefix . $this->user->language_code . ".order.confirm_payment_photo";
                $buttons_texts = [
                    'yes' => $this->config['payment_photo']['yes'][$this->user->language_code],
                    'no' => $this->config['payment_photo']['no'][$this->user->language_code]
                ];

                $this->chat
                    ->deleteMessage($this->user->message_id)
                    ->send(); // удаляем текущее сообщение

                $keyboard = Keyboard::make()->buttons([
                    Button::make($buttons_texts['yes'])
                        ->action('payment_photo')
                        ->param('confirm', 1)
                        ->param('order_id', $order->id)
                        ->param('yes', 1),

                    Button::make($buttons_texts['no'])
                        ->action('payment_photo')
                        ->param('confirm', 1)
                        ->param('order_id', $order->id)
                        ->param('no', 1),
                ]);
                $response = $this->chat
                    ->photo(Storage::path($photo_path))
                    ->html(Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'message_id' => $response->telegraphMessageId()
                ]);
            }
        }
    }

    public function request_rating(): void
    {
        $flag = $this->data->get('rating');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        $template = $this->template_prefix . $this->user->language_code . '.order.request_rating';
        $buttons_texts = [
            'recommend' => $this->config['request_rating']['recommend'][$this->user->language_code],
            'start' => $this->config['request_rating']['start'][$this->user->language_code],
        ];

        $recommend_button = Button::make($buttons_texts['recommend'])
            ->url("https://t.me/share/url?url=https://t.me/rastan_telegraph_bot?start=ref{$this->user->id}");
        $start_button = Button::make($buttons_texts['start'])
            ->action('start');


        if (isset($flag)) {
            $choice = $this->data->get('choice');
            if (isset($choice)) {
                $keyboard = Keyboard::make()->buttons([$recommend_button, $start_button]);
                $order->update(['rating' => $choice]);
                $this->chat
                    ->message(Helper::prepare_template($template, ['order' => $order]))
                    ->edit($this->messageId)
                    ->keyboard($keyboard)
                    ->send();
                // не меняется ни страница, ни месседж_ид
            }
        }

        if (!isset($flag)) {
            $keyboard = Keyboard::make();
            for ($i = 1; $i < 6; $i++) {
                $keyboard->button($i)
                    ->action('request_rating')
                    ->param('rating', 1)
                    ->param('choice', $i)
                    ->param('order_id', $order->id)
                    ->width(0.2);
            }
            $keyboard->buttons([$recommend_button, $start_button]);

            $this->terminate_active_page();
            $response = $this->chat
                ->message(Helper::prepare_template($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
            $this->user->update([
                'page' => 'request_rating',
                'message_id' => $response->telegraphMessageId()
            ]);
        }

    }

    /* Должен вызываться только через фейк-запросы */
    /* и свою кнопку "Продолжить" */
    public function unpaid_orders(): bool
    {
        $flag = $this->data->get('unpaid');

        if (isset($flag)) {
            $page = $this->data->get('page');
            $command = $this->data->get('command');

            if (isset($page)) {
                switch ($page) {
                    case 'start':
                    case 'first_scenario':
                    case 'first_scenario_phone':
                    case 'first_scenario_whatsapp':
                    case 'second_scenario':
                    case 'payment':
                    case 'order_dialogue':
                    case 'request_order_message':
                    case 'message_from_courier':
                    case 'select_payment':
                    case 'notification':
                    case 'payment_photo':
                    case 'request_rating ':
                        $this->start();
                        break;
                    case 'orders':
                    case 'order_accepted':
                    case 'order_wishes':
                    case 'cancel_order':
                    case 'order_canceled':
                        $this->orders();
                        break;
                    case 'about_us':
                        $this->about();
                        break;
                    case 'profile':
                    case 'select_language':
                    case 'profile_change_phone_number':
                    case 'profile_change_whatsapp':
                        $this->profile();
                        break;
                    case 'referrals':
                    case 'addition_bonus':
                    case 'addition_bonus':
                    case 'payment_with_bonuses':
                        $this->referrals();
                        break;
                    case 'support':
                    case 'add_ticket':
                    case 'check_user_tickets':
                    case 'ticket_creation':
                        $this->support();
                        break;
                    default:
                        $this->start();
                }
            }

            if (isset($command)) {
                switch ($command) {
                    case '/start':
                        $this->start();
                        break;
                    case '/about':
                        $this->about();
                        break;
                    case '/orders':
                        $this->orders();
                        break;
                    case '/profile':
                        $this->profile();
                        break;
                    case '/referrals':
                        $this->referrals();
                        break;
                    case '/support':
                        $this->support();
                        break;
                }
            }

            return true;
        }

        if (!isset($flag)) {
            /* получаем неоплаченные заказы */
            $payments = Payment::where('status_id', 1)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('orders')
                        ->whereColumn('orders.id', 'payments.order_id')
                        ->where('orders.user_id', $this->user->id);
                })->get();

            if ($payments->isEmpty()) return false;
            else {
                $template = $this->template_prefix . $this->user->language_code . '.orders.unpaids';
                $template_dataset = [
                    'payments' => $payments,
                    'is_one' => false
                ];

                $buttons_texts = $this->config['unpaid_orders'];
                $buttons = [];

                if ($payments->count() === 1) {
                    $template_dataset['is_one'] = true;
                    $buttons[] = Button::make($buttons_texts['pay'][$this->user->language_code])
                        ->action('payment_page')
                        ->param('order_id', ($payments->first())->order->id);
                } else {
                    foreach ($payments as $payment) {
                        $buttons[] = Button::make("#{$payment->order->id}")
                            ->action('payment_page')
                            ->param('order_id', $payment->order->id);
                    }
                }

                /* Если метод отрабатывает через фейк-реквест, тогда проверяется текущая страница */
                /* Если не через фейк реквест, а через команду был вызван обработчик, где вызвался текущи метод*/
                /* Тогда будет браться текст команды */
                $fake = $this->data->get('fake');

                if (isset($fake)) {
                    $page = $this->user->page;
                    $buttons[] = Button::make($buttons_texts['continue'][$this->user->language_code])
                        ->action('unpaid_orders')
                        ->param('page', $page)
                        ->param('unpaid', 1);
                }

                if (!isset($fake)) {
                    $command = $this->message->text();
                    $buttons[] = Button::make($buttons_texts['continue'][$this->user->language_code])
                        ->action('unpaid_orders')
                        ->param('command', $command)
                        ->param('unpaid', 1);
                }

                $this->terminate_active_page();

                $keyboard = Keyboard::make()->buttons($buttons);
                $response = $this->chat
                    ->message(Helper::prepare_template($template, $template_dataset))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'page' => 'unpaid_orders',
                    'message_id' => $response->telegraphMessageId()
                ]);

                return true;
            }
        }
        return true;
    }

    /* Обработки своих кнопок не будет => без флага */
    public function payment_page(): void
    {
        /* Для возврата с други страниц используется флаг back */
        /* нужен чтобы знать когда редактировать пейдж */
        $back = $this->data->get('back');
        $order_id = $this->data->get('order_id');
        $order = isset($order_id) ? Order::where('id', $order_id)->first() : $this->user->active_order;

        $template = $this->template_prefix . $this->user->language_code . ".order.payment_after_delivered";
        $template_data = [
            'order_services' => OrderServicePivot::where('order_id', $order->id)->get(),
            'price' => $order->price,
            'payment' => [],
        ];
        $buttons_texts = $this->config['payment'];
        $buttons = [];

        $method_id = $order->payment->method_id;
        $status_id = $order->payment->status_id;

        $payment_desc_key = "{$this->user->language_code}_desc";
        $template_data['payment']['method_id'] = $method_id;
        $template_data['payment']['status_id'] = $status_id;

        if (isset($method_id)) $template_data['payment']['desc'] = $order->payment->method->$payment_desc_key;

        /* Если заказ еще ожидает оплаты ИЛИ оплата выбрана курьеру */
        if ($status_id === 1 or $method_id === 1) {
            $selection_button_text = $buttons_texts['select'][$this->user->language_code];

            if (!is_null($method_id)) {
                $selection_button_text = $buttons_texts['change'][$this->user->language_code];
                if ($method_id === 2 or $method_id === 3) {
                    switch ($method_id) {
                        case 3:
                            $template_data['payment']['ru_price'] = $order->price * 0.0058;
                        case 2:
                            $buttons[] = Button::make($buttons_texts['request_photo'][$this->user->language_code])
                                ->action('payment_photo')
                                ->param('request', 1)
                                ->param('order_id', $order->id);
                            break;
                    }
                }
            }

            $buttons[] = Button::make($selection_button_text)
                ->action('select_payment')
                ->param('order_id', $order->id);

        }

        if ($order->status_id === 12) { // пока кура доставляет заказ
            $template = $this->template_prefix . $this->user->language_code . ".order.payment_before_delivered";
            $buttons[] = Button::make($buttons_texts['dialogue'][$this->user->language_code])
                ->action('order_dialogue')
                ->param('order_id', $order->id);
        }

        $keyboard = Keyboard::make()->buttons($buttons);

        if (isset($back)) { // если с кнопки назад значит редактируем инлайн-пейдж с которого вызвано
            $response = $this->chat
                ->edit($this->user->message_id)
                ->message(Helper::prepare_template($template, $template_data))
                ->keyboard($keyboard)
                ->send();
        } else {
            $this->terminate_active_page();
            $response = $this->chat
                ->message(Helper::prepare_template($template, $template_data))
                ->keyboard($keyboard)
                ->send();
            $order->update(['active' => true]);
        }

        $this->user->update([
            'page' => 'payment',
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function payment_with_bonuses(): void
    {
        $flag = $this->data->get('bonuses');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        if (isset($flag)) {
            $price = $order->price;
            $balance = $this->user->balance;
            $with_bonuses = 0;
            if ($balance >= $price) {
                $with_bonuses = $price;
                $balance = $balance - $price;
                $price = 0;
            } else if ($balance < $price) {
                $price = $price - $balance;
                $with_bonuses = $balance;
                $balance = 0;
            }

            $this->user->update(['balance' => $balance]);
            $order->update([
                'price' => $price,
                'bonuses' => $with_bonuses
            ]);

            if ($price === 0) {
                $order->payment->update([
                    'method_id' => 4,
                    'status_id' => 3 // оплачен
                ]);
            }

            if ($price !== 0 or $order->status_id === 12) {
                $fake_dataset = [
                    'action' => 'payment_page',
                    'params' => [
                        'back' => 1,
                        'order_id' => $order->id
                    ]
                ];
                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                (new self($this->user))->handle($fake_request, $this->bot);
            }
        }

        if (!isset($flag)) {
            $template = $this->template_prefix . $this->user->language_code . '.notifications.payment_with_bonuses';
            $template_data = [
                'balance' => $this->user->balance,
                'order' => $order
            ];
            $buttons_texts = [
                'yes' => $this->config['payment_with_bonuses']['yes'][$this->user->language_code],
                'no' => $this->config['payment_with_bonuses']['no'][$this->user->language_code],
            ];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['yes'])
                    ->action('payment_with_bonuses')
                    ->param('bonuses', 1)
                    ->param('order_id', $order->id),

                Button::make($buttons_texts['no'])
                    ->action('payment_page')
                    ->param('order_id', $order->id)
                    ->param('back', 1)

            ]);
            $this->chat
                ->edit($this->messageId)
                ->message(Helper::prepare_template($template, $template_data))
                ->keyboard($keyboard)
                ->send();

            $this->user->update([
                'page' => 'payment_with_bonuses'
            ]);
        }
    }

    // когда сюда попадает всегда актуальный оред_эктив стоит
    // можно попасть только с кнопки!
    public function select_payment(): void
    {
        $flag = $this->data->get('select');
        $order_id = $this->data->get('order_id');
        $order = isset($order_id) ? Order::where('id', $order_id)->first() : $this->user->active_order;

        if (isset($flag)) {
            $choice = $this->data->get('choice');

            if (isset($choice)) {
                if ($choice == 1) { // оплата курьеру
                    $order->payment->update([
                        'method_id' => $choice,
                        'status_id' => 2
                    ]);
                } else if ($choice == 2 or $choice == 3) {
                    $order->payment->update([
                        'method_id' => $choice,
                        'status_id' => 1
                    ]);
                }

                /* отправка назад к инфе о идушем заказе */
                $fake_dataset = [
                    'action' => 'payment_page',
                    'params' => [
                        'back' => 1,
                        'order_id' => $order->id
                    ]
                ];
                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                (new User($this->user))->handle($fake_request, $this->bot);
            }

        }

        if (!isset($flag)) {
            $payment_methods = PaymentMethod::all();
            $template = $this->template_prefix . $this->user->language_code . '.order.select_payment';
            $buttons = [];

            foreach ($payment_methods as $method) {
                if ($order->payment->method_id !== $method->id) {
                    $desc_property = "{$this->user->language_code}_desc";
                    if ($method->id !== 4) {
                        $buttons[] = Button::make($method->$desc_property)
                            ->action('select_payment')
                            ->param('select', 1)
                            ->param('choice', $method->id)
                            ->param('order_id', $order->id);
                    } else {
                        $buttons[] = Button::make($method->$desc_property)
                            ->action('payment_with_bonuses')
                            ->param('order_id', $order->id);
                    }
                }
            } // end foreach

            $buttons[] = Button::make($this->config['back'][$this->user->language_code])
                ->action('payment_page')
                ->param('back', 1)
                ->param('order_id', $order->id);

            $keyboard = Keyboard::make()->buttons($buttons);

            $this->chat
                ->edit($this->messageId)
                ->message(view($template))
                ->keyboard($keyboard)
                ->send();

            $this->user->update(['page' => 'select_payment']);
        }
    }

    public function order_dialogue(): void
    {
        $flag = $this->data->get('dialogue');
        $order_id = $this->data->get('order_id');
        $order = isset($order_id) ? Order::where('id', $order_id)->first() : $this->user->active_order;

        $buttons_texts = [
            'write' => $this->config['order_dialogue']['write'][$this->user->language_code],
            'pay' => $this->config['order_dialogue']['pay'][$this->user->language_code],
            'change' => $this->config['order_dialogue']['change'][$this->user->language_code],
            'reply' => $this->config['order_dialogue']['reply'][$this->user->language_code],
            'close' => $this->config['order_dialogue']['close'][$this->user->language_code],
            'open' => $this->config['order_dialogue']['open'][$this->user->language_code]
        ];

        if (isset($flag)) {
            $write = $this->data->get('write');
            $get_message = $this->data->get('get');

            if (isset($write)) { // просьба написать сообщение
                $template = $this->template_prefix . $this->user->language_code . '.order.request_order_message';
                $back_button = $this->config['request_order_message'][$this->user->language_code];

                $keyboard = null;
                if ($this->user->page === 'message_from_courier') { // возврат на сообщение курьера
                    $keyboard = Keyboard::make()->button($back_button)
                        ->action('order_dialogue')
                        ->param('dialogue', 1)
                        ->param('get', 1)
                        ->param('order_id', $order->id);
                }

                if ($this->user->page === 'order_dialogue') { // возврат на общение с курьером
                    $keyboard = Keyboard::make()->button($back_button)
                        ->action('order_dialogue')
                        ->param('order_id', $order->id);
                }

                $this->chat
                    ->edit($this->messageId)
                    ->message(Helper::prepare_template($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update(['page' => 'request_order_message']);
            }

            if (isset($get_message)) { // если прилетел фейк запрос через наблюдатель когда курьер отправил сообщение
                $new_order_message = OrderMessage::where('order_id', $order->id)
                    ->orderBy('created_at', 'desc')
                    ->first(); // получаем последнее сообщение

                $template = $this->template_prefix . $this->user->language_code . '.notifications.received_order_message';
                $buttons = [];

                $buttons[] = Button::make($buttons_texts['reply'])
                    ->action('order_dialogue')
                    ->param('dialogue', 1)
                    ->param('write', 1)
                    ->param('order_id', $order->id)
                    ->width(0.5);

                $buttons[] = Button::make($buttons_texts['close'])
                    ->action('start')
                    ->width(0.5);

                $buttons[] = Button::make($buttons_texts['open'])
                    ->action('order_dialogue')
                    ->param('order_id', $order->id)
                    ->width(0.5);

                if ($order->payment->status_id === 1) {
                    if ($order->payment->method_id !== 1) {
                        $buttons[] = Button::make($buttons_texts['pay'])
                            ->action('payment_page')
                            ->param('back', 1);
                    } else {
                        $buttons[] = Button::make($buttons_texts['change'])
                            ->action('payment_page')
                            ->param('back', 1);
                    }
                }

                $keyboard = Keyboard::make()->buttons($buttons);

                $this->terminate_active_page();

                $response = $this->chat
                    ->message(Helper::prepare_template($template, [
                        'order' => $order,
                        'order_message' => $new_order_message,
                    ]))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'page' => 'message_from_courier',
                    'message_id' => $response->telegraphMessageId()
                ]);

                $order->update(['active' => true]);
            }
        }

        if (!isset($flag)) {
            $order_messages = OrderMessage::where('order_id', $order->id)->get();

            $template = $this->template_prefix . $this->user->language_code . '.order.dialogue';
            $template_dataset = [
                'order_messages' => $order_messages,
                'current_chat_id' => $this->chat->chat_id,
                'order' => $order
            ];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['write'])
                    ->action('order_dialogue')
                    ->param('dialogue', 1)
                    ->param('write', 1)
                    ->param('order_id', $order->id),

                Button::make($this->config['back'][$this->user->language_code])
                    ->action('payment_page')
                    ->param('back', 1)
                    ->param('order_id', $order->id)

            ]);

            if (isset($this->callbackQuery)) { // значит прилетело с кнопки => редактируем пред.инлайн-пейдж
                $response = $this->chat
                    ->edit($this->messageId)
                    ->message(Helper::prepare_template($template, $template_dataset))
                    ->keyboard($keyboard)
                    ->send();
            } else { // значит зашел после ввода сообщения (при $this->message) нужно удалить активное окно
                $this->terminate_active_page(false);
                $response = $this->chat
                    ->message(Helper::prepare_template($template, $template_dataset))
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'order_dialogue',
                'message_id' => $response->telegraphMessageId()
            ]);
        }
    }

    public function referrals(): void
    {
        if ($this->check_for_language_code()) return;
        $flag = $this->data->get('referrals');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        if (!isset($flag)) {
            if (isset($this->message)) {
                if ($this->unpaid_orders()) return;
            }
            $this->terminate_active_page();

            $buttons_texts = [
                'recommend' => $this->config['referrals']['recommend'][$this->user->language_code],
                'new_order' => $this->config['referrals']['new_order'][$this->user->language_code],
                'continue_order' => $this->config['referrals']['continue_order'][$this->user->language_code],
                'info' => $this->config['referrals']['info'][$this->user->language_code]
            ];
            $template = $template_prefix_lang . '.referrals.main';
            $start_order_button = null;
            if ($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                $start_order_button = Button::make($buttons_texts['continue_order'])
                    ->action('start')
                    ->param('start', 1);
            } else {
                $start_order_button = Button::make($buttons_texts['new_order'])->action('start');
            }

            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['recommend'])
                    ->url("https://t.me/share/url?url=https://t.me/LaundryPhuket_Bot?start=ref{$this->user->id}"),
                Button::make($buttons_texts['info'])
                    ->action('referrals')
                    ->param('referrals', 1)
                    ->param('info', 1),
                $start_order_button
            ]);

            $response = null;
            if (isset($this->message)) {
                $response = $this->chat
                    ->photo(Storage::path("User/{$this->user->id}/qr.png"))
                    ->html(Helper::prepare_template($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat
                    ->photo(Storage::path("User/{$this->user->id}/qr.png"))
                    ->html(Helper::prepare_template($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'referrals',
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if (isset($flag)) {
            $info = $this->data->get('info');
            $back_button_text = $this->config['referrals']['back'][$this->user->language_code];
            $back_button = Button::make($back_button_text)->action('referrals');

            $template = $template_prefix_lang . '.referrals.info';
            if (isset($info)) {
                $referrals_amount = $this->user->referrals()->count();

                $this->chat
                    ->deleteMessage($this->user->message_id)
                    ->send();

                $response = $this->chat
                    ->message(Helper::prepare_template($template,
                        [
                            'referrals_amount' => $referrals_amount,
                            'bonuses' => $this->user->balance
                        ]
                    ))
                    ->keyboard(Keyboard::make()->buttons([$back_button]))
                    ->send();

                $this->user
                    ->update([
                        'message_id' => $response->telegraphMessageId()
                    ]);
            }
        }
    }

    public function profile_change_handler(): void
    {
        $page = $this->user->page;
        if ($page === 'profile_change_phone_number') {
            $phone_number = $this->message->text();

            if (mb_strlen($phone_number) >= 32) {
                $phone_number = null;
            } else {
                $phone_number = ((int)$phone_number) ? $phone_number : null;
            }

            $this->user->update([
                'phone_number' => $phone_number,
            ]);

        } else if ($page === 'profile_change_whatsapp') {
            $whatsapp_number = $this->message->text();

            if (mb_strlen($whatsapp_number) >= 32) {
                $whatsapp_number = null;
            } else {
                $whatsapp_number = ((int)$whatsapp_number) ? $whatsapp_number : null;
            }

            $this->user->update([
                'whatsapp' => $whatsapp_number,
            ]);
        }

        $this->profile();
    }

    public function profile(): void
    {
        if ($this->check_for_language_code()) return;
        $flag = $this->data->get('profile');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        if (!isset($flag)) {
            if (isset($this->message)) {
                if ($this->unpaid_orders()) return;
            }
            $buttons_texts = [
                'new_order' => $this->config['profile']['new_order'][$this->user->language_code],
                'continue_order' => $this->config['profile']['continue_order'][$this->user->language_code],
                'phone_number' => $this->config['profile']['phone_number'][$this->user->language_code],
                'whatsapp' => $this->config['profile']['whatsapp'][$this->user->language_code],
                'language' => $this->config['profile']['language'][$this->user->language_code],
            ];
            $template = $template_prefix_lang . '.profile.main';
            $start_order_button = null;
            if ($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                $start_order_button = Button::make($buttons_texts['continue_order'])
                    ->action('start')
                    ->param('start', 1);
            } else {
                $start_order_button = Button::make($buttons_texts['new_order'])->action('start');
            }

            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['phone_number'])
                    ->action('profile')
                    ->param('profile', 1)
                    ->param('choice', 1),
                Button::make($buttons_texts['whatsapp'])
                    ->action('profile')
                    ->param('profile', 1)
                    ->param('choice', 2),
                Button::make($buttons_texts['language'])
                    ->action('profile')
                    ->param('profile', 1)
                    ->param('choice', 3),
                $start_order_button
            ]);

            $response = null;
            if (isset($this->message)) {
                $this->terminate_active_page();
                $response = $this->chat
                    ->message(Helper::prepare_template($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat
                    ->edit($this->user->message_id)
                    ->message(Helper::prepare_template($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'profile',
                'message_id' => $response->telegraphMessageId()
            ]);

        }

        if (isset($flag)) {
            $choice = $this->data->get('choice');
            $back_button_text = $this->config['profile']['back'][$this->user->language_code];
            $keyboard = Keyboard::make()->button($back_button_text)->action('profile');

            if ($choice == 1 or $choice == 2) {
                $template = null;
                if ($choice == 1) {
                    $template = $template_prefix_lang . '.profile.write_phone_number';
                    $page = 'profile_change_phone_number';
                } else if ($choice == 2) {
                    $template = $template_prefix_lang . '.profile.write_whatsapp';
                    $page = 'profile_change_whatsapp';
                }

                $this->chat
                    ->edit($this->user->message_id)
                    ->message(Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'page' => $page,
                ]);
            } else if ($choice == 3) {
                $this->select_language();
            }
        }
    }

    public function show_order_info(): void
    {
        $flag = $this->data->get('show_order_info');
        $template = $this->template_prefix . $this->user->language_code . '.order.info';

        if (isset($flag)) {
            $order_id = $this->data->get('id');
            $order = Order::where('id', $order_id)->first();
            $status_id = $order->status_id;
            $status_1 = $order->statuses->where('id', 1)->first();

            $buttons_texts = [
                'recommend' => $this->config['order_info']['recommend'][$this->user->language_code],
                'back' => $this->config['order_info']['back'][$this->user->language_code]
            ];
            $recommend_button = Button::make($buttons_texts['recommend'])->action('referrals');
            $back_button = Button::make($buttons_texts['back'])
                ->action('orders')
                ->param('orders', 1); // нужно еще добавить choice чтобы знать с какого типа назад
            if ($status_id !== 4) {
                $buttons_texts = [
                    'wishes' => $this->config['order_info']['wishes'][$this->user->language_code],
                    'cancel' => $this->config['order_info']['cancel'][$this->user->language_code],
                ];
                $back_button = $back_button->param('choice', 1);
                $buttons = [];

                $buttons[] = Button::make($buttons_texts['wishes'])
                    ->action('write_order_wishes')
                    ->param('write_order_wishes', 1);

                if ($order->status_id < 5) {
                    $buttons[] = Button::make($buttons_texts['cancel'])
                        ->action('cancel_order')
                        ->param('cancel_order', 1);
                }

                $buttons[] = $recommend_button;
                $buttons[] = $back_button;
                $keyboard = Keyboard::make()->buttons($buttons);
                if (isset($this->message)) {
                    $this->terminate_active_page(false);
                    $response = $this->chat
                        ->message(Helper::prepare_template($template, [
                            'order' => $order,
                            'status_1' => $status_1
                        ]))
                        ->keyboard($keyboard)
                        ->send();

                    $this->user->update([
                        'page' => 'orders',
                        'message_id' => $response->telegraphMessageId()
                    ]);
                } else {
                    $this->chat->edit($this->user->message_id)
                        ->message(Helper::prepare_template($template, [
                            'order' => $order,
                            'status_1' => $status_1
                        ]))
                        ->keyboard($keyboard)
                        ->send();

                    $order->update([
                        'active' => true
                    ]);
                }
            } else if ($status_id === 4 and $order->reason_id !== 5) {
                $back_button = $back_button->param('choice', 2);

                $this->chat->edit($this->user->message_id)
                    ->message(Helper::prepare_template($template, [
                        'order' => $order,
                        'status_1' => $status_1
                    ]))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            $recommend_button,
                            $back_button
                        ])
                    )->send();
            } // else остальные статусы
        }
    }

    public function orders(): void
    {
        if ($this->check_for_language_code()) return;
        $flag = $this->data->get('orders');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        $buttons_text = [
            'new_order' => $this->config['orders']['new_order'][$this->user->language_code],
            'continue_order' => $this->config['orders']['continue_order'][$this->user->language_code],
            'active' => $this->config['orders']['active'][$this->user->language_code],
            'completed' => $this->config['orders']['completed'][$this->user->language_code],
            'back' => $this->config['orders']['back'][$this->user->language_code]
        ];
        $start_order_button = null;


        if (!isset($flag)) {

            if (isset($this->message)) {
                if ($this->unpaid_orders()) return;
                $this->terminate_active_page();
            }

            $orders = Order::where('user_id', $this->user->id)
                ->whereNot(function (Builder $query) {
                    $query
                        ->where('status_id', 4)
                        ->whereNot('reason_id', 5);
                })
                ->orderBy(OrderStatusPivot::select('created_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->where('status_id', 1)
                    ->limit(1)
                    , 'desc')->get();


            $response = null;
            if ($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                $start_order_button = Button::make($buttons_text['continue_order'])
                    ->action('start')
                    ->param('start', 1);
            } else {
                $start_order_button = Button::make($buttons_text['new_order'])->action('start');
            }

            if ($orders->isEmpty()) {
                $no_orders_template = $template_prefix_lang . '.orders.no_orders';
                $response = $this->chat
                    ->message(Helper::prepare_template($no_orders_template))
                    ->keyboard(Keyboard::make()->buttons([$start_order_button]))
                    ->send();
            } else {
                $keyboard = Keyboard::make()->buttons([
                    Button::make($buttons_text['active'])
                        ->action('orders')
                        ->param('orders', 1)
                        ->param('choice', 1),
                    Button::make($buttons_text['completed'])
                        ->action('orders')
                        ->param('orders', 1)
                        ->param('choice', 2),
                    $start_order_button
                ]);

                $orders_template = $template_prefix_lang . '.orders.all';
                $view = Helper::prepare_template($orders_template, ['orders' => $orders]);

                if (isset($this->message)) {
                    $response = $this->chat
                        ->message($view)
                        ->keyboard($keyboard)
                        ->send();
                } else if (isset($this->callbackQuery)) {
                    $this->chat
                        ->edit($this->user->message_id)
                        ->message($view)
                        ->keyboard($keyboard)
                        ->send();
                    return;
                }
            }

            $this->user->update([
                'page' => 'orders',
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if (isset($flag)) {
            $choice = $this->data->get('choice');
            $back_button = Button::make($buttons_text['back'])->action('orders');

            if ($choice == 1) { // нажатие на активные

                if (isset($this->user->active_order)) { // если возвращаемся назад с order_info
                    $this->user->active_order->update([
                        'active' => false
                    ]);
                }

                $orders = Order::where('user_id', $this->user->id)
                    ->whereNotIn('status_id', [4])
                    ->orWhere(function (Builder $query) {
                        $query
                            ->where('user_id', $this->user->id)
                            ->where('status_id', 4)
                            ->where('reason_id', 5);
                    })
                    ->orderBy(OrderStatusPivot::select('created_at')
                        ->whereColumn('order_id', 'orders.id')
                        ->where('status_id', 1)
                        ->limit(1)
                        , 'desc')
                    ->get();

                if ($orders->isEmpty()) {
                    $no_active_orders_template = $template_prefix_lang . '.orders.no_active_orders';
                    $this->chat->edit($this->user->message_id)
                        ->message(Helper::prepare_template($no_active_orders_template))
                        ->keyboard(Keyboard::make()->row([$back_button]))->send();
                } else {
                    $orders_buttons_line = [];
                    foreach ($orders as $order) {
                        $button = Button::make("#{$order->id}");

                        if ($order->status_id === 4 and $order->reason_id === 5) {
                            $button
                                ->action('start')
                                ->param('start', 1);
                        } else {
                            $button
                                ->action('show_order_info')
                                ->param('show_order_info', 1)
                                ->param('id', $order->id);
                        }
                        $orders_buttons_line[] = $button;
                    }
                    $orders_buttons_slices = collect($orders_buttons_line)->chunk(2)->toArray();
                    $keyboard = Keyboard::make();
                    foreach ($orders_buttons_slices as $orders_buttons) {
                        $keyboard = $keyboard->row($orders_buttons);
                    }
                    $keyboard->row([$back_button]);

                    $orders_template = $template_prefix_lang . '.orders.active';
                    $view = Helper::prepare_template($orders_template, ['orders' => $orders]);
                    $this->chat->edit($this->user->message_id)
                        ->message($view)
                        ->keyboard($keyboard)
                        ->send();
                }
            } else if ($choice == 2) { // завершенные заявки
                $orders = Order::where('status_id', 14)->get();
                $template = $template_prefix_lang . '.orders.completed';
                $buttons = [$back_button];

                foreach ($orders as $order) {
                    if (!isset($order->rating)) {
                        $buttons[] = Button::make("#{$order->id}")
                            ->action('request_rating')
                            ->param('order_id', $order->id);
                    }
                }

                $keyboard = Keyboard::make()->buttons($buttons);
                $response = $this->chat
                    ->edit($this->user->message_id)
                    ->message(Helper::prepare_template($template, ['orders' => $orders]))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'page' => 'completed_orders',
                    'message_id' => $response->telegraphMessageId()
                ]);
            }

        }

    }

    public function about(): void // можно попасть только с команды /about
    {
        if ($this->check_for_language_code()) return;
        if (isset($this->message)) {
            if ($this->unpaid_orders()) return;
        }
        $this->terminate_active_page();

        $template_prefix_lang = $this->template_prefix . $this->user->language_code;
        $buttons_text = [
            'new_order' => $this->config['about_us']['new_order'][$this->user->language_code],
            'continue_order' => $this->config['about_us']['continue_order'][$this->user->language_code],
        ];

        $start_order_button = null;
        if ($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
            $start_order_button = Button::make($buttons_text['continue_order'])
                ->action('start')
                ->param('start', 1);
        } else {
            $start_order_button = Button::make($buttons_text['new_order'])->action('start');
        }

        $template_about = $template_prefix_lang . '.about_us';
        $response = $this->chat
            ->message((string)Helper::prepare_template($template_about))
            ->keyboard(Keyboard::make()->buttons([
                $start_order_button
            ]))
            ->send();

        $this->user->update([
            'page' => 'about_us',
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function start(string $ref = null): void
    {
        if ($this->check_for_language_code()) return;
        $flag = $this->data->get('start');
        $ref_flag = false;
        if (isset($ref) and $ref !== '/start') $ref_flag = true;

        if (!isset($flag)) {
            if (isset($this->user)) {

                if (isset($this->message)) {
                    if ($this->unpaid_orders()) return;
                }

                $template_prefix_lang = $this->template_prefix . $this->user->language_code;
                $template_start = $template_prefix_lang . '.start';

                $buttons_text = [
                    'new_order' => $this->config['start']['new_order'][$this->user->language_code],
                    'continue_order' => $this->config['start']['continue_order'][$this->user->language_code],
                    'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                ];

                $start_order_button = null;
                if ($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                    $start_order_button = Button::make($buttons_text['continue_order'])
                        ->action('start')
                        ->param('start', 1);
                } else {
                    $start_order_button = Button::make($buttons_text['new_order'])
                        ->action('start')
                        ->param('start', 1);
                }

                $keyboard = Keyboard::make()->buttons([
                    $start_order_button,
                    Button::make($buttons_text['reviews'])->url('https://t.me/laundrybot_feedback')
                ]);

                if (isset($this->user->message_id)) {
                    $this->terminate_active_page();
                }

                $response = $this->chat
                    ->message((string)Helper::prepare_template($template_start))
                    ->keyboard($keyboard)
                    ->send();

                if (isset($order)) { // если активная есть, допустим после отмены заказа окно на котором нет активной заявки
                    $order->update([
                        'active' => false
                    ]);
                }

                $this->user->update([
                    'message_id' => $response->telegraphMessageId(),
                    'page' => 'start'
                ]);

            } else { // если юзер зашел первый раз
                $chat_id = $this->message->from()->id();
                $username = $this->message->from()->username();

                $this->user = UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                ]);

                if ($ref_flag) {
                    $inviter_id = trim(str_replace('ref', '', $ref));
                    $invited_id = $this->user->id;

                    Referral::create([
                        'inviter_id' => $inviter_id,
                        'invited_id' => $invited_id
                    ]);
                }

                QR::generate_referrals_qr($this->user);

                $this->select_language();
            }
        }

        if (isset($flag)) { // когда отправка с кнопки с флагом start
            $order = Order::where('user_id', $this->user->id)
                ->where('reason_id', 5)
                ->first();
            $step = 1;

            if (!isset($order)) {
                $order = Order::create([
                    'user_id' => $this->user->id,
                    'status_id' => 1,
                    'active' => true
                ]);
            } else {
                $step = $order->last_step;
                $order = Order::withoutEvents(function () use ($order) {
                    OrderStatusPivot::where('order_id', $order->id)
                        ->where('status_id', 4)
                        ->delete();

                    $order->update([
                        'status_id' => 1,
                        'reason_id' => null,
                        'active' => true,
                        'last_step' => null
                    ]);
                });
            }

            $scenario = null;
            $orders_amount = Order::where('user_id', $this->user->id)->count();
            $phone_number = isset($this->user->phone_number);
            $whatsapp = isset($this->user->whatsapp);
            if ($orders_amount === 1) {
                switch ($step) {
                    case 1:
                    case 2:
                        if ($phone_number and $whatsapp) $scenario = 'second_scenario';
                        else if ($phone_number and !$whatsapp) $scenario = 'first_scenario_whatsapp';
                        else if (!$phone_number and $whatsapp) $scenario = 'first_scenario_phone';
                        else if (!$phone_number and !$whatsapp) $scenario = 'first_scenario';
                        break;
                    case 3:
                    case 4:
                    case 5:
                        if ($phone_number and $whatsapp) {
                            $scenario = 'first_scenario';
                            $step = 5;
                            break;
                        } else if ($phone_number and !$whatsapp) {
                            $scenario = 'first_scenario_whatsapp';
                        } else if (!$phone_number and $whatsapp) {
                            $scenario = 'first_scenario_phone';
                        } else if (!$phone_number and !$whatsapp) {
                            $scenario = 'first_scenario';
                        }
                        $step = 3;
                        break;
                }
            } else {
                switch ($step) {
                    case 1:
                    case 2:
                    case 3:
                        if ($phone_number) $scenario = 'second_scenario';
                        else if (!$phone_number) $scenario = 'first_scenario_phone';
                        break;
                    case 4:
                        if ($phone_number) $scenario = 'first_scenario_phone';
                        else if (!$phone_number) {
                            $scenario = 'first_scenario_phone';
                            $step = 3;
                        }
                }
            }

            $this->user->update([
                'page' => $scenario,
                'step' => $step
            ]);

            $this->handle_scenario_request();
        }

    }

    public function select_language(): void
    {
        $flag = $this->data->get('select_language');

        if (isset($flag)) { // если прилетели данные именно при выборе языка (нажатие на кнопку выбора языка)
            $language_code = $this->data->get('language_code');

            $this->user->update([
                'language_code' => $language_code,
            ]);

            $page = $this->data->get('page');

            if (isset($page)) {
                switch ($page) {
                    case 1:
                        $this->start();
                        break;
                    case 2:
                        $this->profile();
                        break;
                }
            }

        }

        if (!isset($flag)) {
            $buttons = [
                'russia' => $this->config['select_language']['russia'],
                'english' => $this->config['select_language']['english'],
            ];
            $template = $this->template_prefix . 'select_language';
            $button_select_en = Button::make($buttons['english'])
                ->action('select_language')
                ->param('select_language', 1)
                ->param('language_code', 'en');

            $button_select_ru = Button::make($buttons['russia'])
                ->action('select_language')
                ->param('select_language', 1)
                ->param('language_code', 'ru');
            $page = $this->user->page;

            if (isset($page) and $page !== 'select_language') {
                $back_button_text = $this->config['select_language']['back'][$this->user->language_code];
                $back_button = Button::make($back_button_text);

                if ($page === 'profile') {
                    $button_select_ru = $button_select_ru->param('page', 2);
                    $button_select_en = $button_select_en->param('page', 2);
                    $back_button = $back_button->action('profile');
                }

                $keyboard = Keyboard::make()->buttons([
                    $button_select_en,
                    $button_select_ru,
                    $back_button
                ]);

                $this->chat
                    ->edit($this->user->message_id)
                    ->message((string)Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

            } else if (isset($page) and $page === 'select_language') {
                $keyboard = Keyboard::make()->buttons([
                    $button_select_en->param('page', 1),
                    $button_select_ru->param('page', 1),
                ]);

                $this->terminate_active_page();

                $response = $this->chat
                    ->message((string)Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'message_id' => $response->telegraphMessageId()
                ]);
            } else {
                $keyboard = Keyboard::make()->buttons([
                    $button_select_en->param('page', 1),
                    $button_select_ru->param('page', 1),
                ]);

                $response = $this->chat
                    ->message((string)Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'message_id' => $response->telegraphMessageId()
                ]);
            }

            $this->user->update([
                'page' => 'select_language',
            ]);
        }
    }

    public function handle_scenario_request(): void
    {
        $page = $this->user->page; // получаем имя сценария
        $step = $this->user->step; // получаем шаг (может быть null)

        if ($this->user->message_id) {
            $this->chat->deleteMessage($this->user->message_id)->send();
        }

        if ($page === 'first_scenario') {
            $steps_amount = 5;
            switch ($step) {
                case 1:
                    $this->request_geo($step, $steps_amount);
                    break;
                case 2:
                    $this->request_address_desc($step, $steps_amount);
                    break;
                case 3:
                    $this->request_contact($step, $steps_amount);
                    break;
                case 4:
                    $this->request_whatsapp($step, $steps_amount);
                    break;
                case 5:
                    $this->request_accepted_order($step, $steps_amount);
                    break;
            }
        } else if ($page === 'second_scenario') {
            $steps_amount = 3;
            switch ($step) {
                case 1:
                    $this->request_geo($step, $steps_amount);
                    break;
                case 2:
                    $this->request_address_desc($step, $steps_amount);
                    break;
                case 3:
                    $this->request_accepted_order($step, $steps_amount);
                    break;
            }
        } else if ($page === 'first_scenario_phone' or $page === 'first_scenario_whatsapp') {
            $steps_amount = 4;
            switch ($step) {
                case 1:
                    $this->request_geo($step, $steps_amount);
                    break;
                case 2:
                    $this->request_address_desc($step, $steps_amount);
                    break;
                case 3:
                    if ($page === 'first_scenario_phone') $this->request_contact($step, $steps_amount);
                    else if ($page === 'first_scenario_whatsapp') $this->request_whatsapp($step, $steps_amount);
                    break;
                case 4:
                    $this->request_accepted_order($step, $steps_amount);
                    break;

            }
        }
    }

    public function handle_scenario_response(): void
    {
        $page = $this->user->page; // получаем имя сценария
        $step = $this->user->step; // получаем шаг (может быть null)

        if ($page === 'first_scenario') {
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
                case 3:
                    $this->contact_handler();
                    break;
                case 4:
                    $this->whatsapp_handler();
                    break; // обработчик пятого шага указан в кнопке
            }
        } else if ($page === 'second_scenario') {
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
            }

        } else if ($page === 'first_scenario_phone' or $page === 'first_scenario_whatsapp') {
            $steps_amount = 4;
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
                case 3:
                    if ($page === 'first_scenario_phone') $this->contact_handler();
                    else if ($page === 'first_scenario_whatsapp') $this->whatsapp_handler();
                    break;
            }
        }
    }

    public function cancel_order(): void
    {
        $flag = $this->data->get('cancel_order');
        $language_code = $this->user->language_code;
        $template_prefix_lang = $this->template_prefix . $language_code;

        if ($flag) {
            $order = $this->user->active_order;
            if ($order->status_id > 3) {
                $messages = [
                    'ru' => '❌Курьер забрал вещи, заказ отменить нельзя!',
                    'en' => '❌The courier took the items, the order cannot be cancelled!'
                ];
                $message = $messages[$this->user->language_code];
                $this->chat->message($message)->send();
                return;
            }
            if ($this->user->page !== 'cancel_order') {
                $buttons = [
                    'check_bot' => $this->config['cancel_order']['check_bot'][$language_code],
                    'changed_my_mind' => $this->config['cancel_order']['changed_my_mind'][$language_code],
                    'quality' => $this->config['cancel_order']['quality'][$language_code],
                    'expensive' => $this->config['cancel_order']['expensive'][$language_code],
                    'back' => $this->config['cancel_order']['back'][$language_code]
                ];
                $template = $template_prefix_lang . '.order.cancel';

                $keyboard = Keyboard::make()
                    ->button($buttons['check_bot'])
                    ->action('cancel_order')
                    ->param('cancel_order', 1)
                    ->param('choice', 1)
                    ->width(0.5)
                    ->button($buttons['changed_my_mind'])
                    ->action('cancel_order')
                    ->param('cancel_order', 1)
                    ->param('choice', 2)
                    ->width(0.5)
                    ->button($buttons['quality'])
                    ->action('cancel_order')
                    ->param('cancel_order', 1)
                    ->param('choice', 3)
                    ->width(0.5)
                    ->button($buttons['expensive'])
                    ->action('cancel_order')
                    ->param('cancel_order', 1)
                    ->param('choice', 4)
                    ->width(0.5);

                if ($this->user->page === 'order_accepted') {
                    $keyboard = $keyboard
                        ->button($buttons['back'])
                        ->action('order_accepted_handler')
                        ->param('order_accepted_handler', 1);
                }

                if ($this->user->page === 'orders') {
                    $keyboard = $keyboard
                        ->button($buttons['back'])
                        ->action('orders')
                        ->param('orders', 1)
                        ->param('choice', 1);
                }

                $this->chat
                    ->edit($this->user->message_id)
                    ->message(Helper::prepare_template($template))
                    ->keyboard($keyboard)
                    ->send();

                $this->user->update([
                    'page' => 'cancel_order'
                ]);
            } else {
                $choice = $this->data->get('choice');
                $order = $this->user->active_order;

                $buttons = [
                    'start' => $this->config['order_canceled']['start'][$language_code],
                    'recommend' => $this->config['order_canceled']['recommend'][$language_code]
                ];
                $template = $template_prefix_lang . '.order.canceled';
                $this->chat
                    ->edit($this->messageId)
                    ->message((string)Helper::prepare_template($template))
                    ->keyboard(Keyboard::make()
                        ->button($buttons['start'])->action('start')
                        ->button($buttons['recommend'])->action('referrals')->param('choice', 2)
                    )
                    ->send();

                $order->update([
                    'reason_id' => $choice,
                    'status_id' => 4,
                    'active' => false
                ]);

                $this->user->update([
                    'page' => 'order_canceled'
                ]);
            }

        }
    }

    public function write_order_wishes(): void
    {
        $flag = $this->data->get('write_order_wishes');
        $order = $this->user->active_order;

        if ($flag) {
            $button = $this->config['order_wishes'][$this->user->language_code];
            $template = $this->template_prefix . $this->user->language_code . '.order.wishes';

            $keyboard = Keyboard::make();
            $step = null;
            if ($this->user->page === 'orders') {
                $step = 2;
                $keyboard = $keyboard->button($button)
                    ->action('show_order_info')
                    ->param('show_order_info', 1)
                    ->param('id', $order->id);
            }

            if ($this->user->page === 'order_accepted') {
                $step = 1;
                $keyboard = $keyboard->button($button)
                    ->action('order_accepted_handler')
                    ->param('order_accepted_handler', 1);
            }

            if ($this->user->page === 'order_pickup_notification') {
                $step = 1;
                $keyboard = $keyboard->button($button)
                    ->action('order_picked_up')
                    ->param('order_id', $order->id);
            }

            $response = $this->chat->edit($this->user->message_id)
                ->message(Helper::prepare_template($template, ['order_id' => $order->id]))
                ->keyboard($keyboard)
                ->send();

            $this->user->update([
                'page' => 'order_wishes',
                'step' => $step,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if (!$flag) {
            $wishes = $this->message->text();

            $order->update([
                'wishes' => $wishes
            ]);

            switch ($this->user->step) {
                case 1:
                    $this->order_accepted_handler();
                    break;
                case 2:
                    $this->data->put('show_order_info', 1);
                    $this->data->put('id', $order->id);
                    $this->show_order_info();
                    break;
            }

        }
    }

    use Traits\SupportUserTrait;
    use Traits\UserMessageTrait;

    public function support(): void
    {
        if ($this->check_for_language_code()) return;
        if ($this->message) {
            $this->terminate_active_page();
        }
        $this->delete_active_page_message();
        $this->support_start();
    }

    public function handle_ticket_request(): void
    {
        $step = $this->user->step;

        switch ($step) {
            case 1:
                $this->create_ticket();
                break;
            case 2:
                $this->ticket_add_photo();
                break;
        }
    }

    public function handle_ticket_response(): void
    {
        $step = $this->user->step;
        switch ($step) {
            case 1:
                $this->ticket_add_text_handler();
                break;
            case 2:
                $this->ticket_add_photo_handler();
                break;
        }
    }

    public function order_picked_up(): void
    {
        $this->terminate_active_page();
        $order_id = $this->data->get('order_id');
        $order = isset($order_id) ? Order::where('id', $order_id)->first() : $this->user->active_order;
        Log::debug($this->user->active_order);
        $order->update([
            'active' => 1
        ]);

        $order_status = OrderStatusPivot::where('order_id', $order->id)
            ->where('status_id', 5)
            ->first();
        $picked_time = (new Carbon($order_status->created_at))->format('Y-m-d H:i');
        $view = view("$this->template_prefix.{$this->user->language_code}.notifications.order_pickuped", [
            'order' => $order,
            'picked_time' => $picked_time
        ]);

        $buttons = config('buttons.user')['order_pickup_notification'];
        $keyboard = Keyboard::make()->buttons([
            Button::make($buttons['wishes'][$this->user->language_code])
                ->action('write_order_wishes')
                ->param('write_order_wishes', 1)
                ->param('order_id', $order_id),
            Button::make($buttons['recommend'][$this->user->language_code])
                ->action('referrals')
        ]);

        $response = $this->chat->message($view)->keyboard($keyboard)->send();
        $this->user->update([
            'page' => 'order_pickup_notification',
            'message_id' => $response->telegraphMessageId()
        ]);
    }


    protected function handleChatMessage(Stringable $text): void
    {
        $photos = $this->message->photos();
        $text = $this->message->text();
        $page = $this->user->page;

        if (isset($text) and $photos->isEmpty()) {
            if (isset($page)) {
                switch ($page) {
                    case 'first_scenario':
                    case 'second_scenario':
                    case 'first_scenario_whatsapp':
                    case 'first_scenario_phone':
                        $this->handle_scenario_response();
                        break;
                    case 'order_wishes':
                        $this->write_order_wishes();
                        break;
                    case 'profile_change_phone_number':
                    case 'profile_change_whatsapp':
                        $this->profile_change_handler();
                        break;
                }

                if ($page === 'request_order_message') {
                    $order = $this->user->active_order;
                    OrderMessage::create([
                        'order_id' => $order->id,
                        'sender_chat_id' => $this->user->chat_id,
                        'text' => $text
                    ]);

                    $this->order_dialogue();
                    /* После создания сообщения в БД будет отправка уведомления курьеру через наблюдатель */
                }
            }
        }

        if (isset($photos) and $photos->isNotEmpty()) {
            if ($page === 'payment_photo') {
                $from = $this->message->from();
                $order = $this->user->active_order;

                $message_timestamp = $this->message->date()->timestamp;
                $last_message_timestamp = $from->storage()->get('payment_photo_timestamp');
                if ($message_timestamp !== $last_message_timestamp) {
                    $photo = $this->save_photo($photos, $order);
                    $from->storage()->set('payment_photo_id', $photo->id());
                }
                $from->storage()->set('payment_photo_timestamp', $message_timestamp);

                $fake_dataset = [
                    'action' => 'payment_photo',
                    'params' => [
                        'confirm' => 1,
                        'order_id' => $order->id
                    ]
                ];

                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                (new self($order->user))->handle($fake_request, $this->bot);
            }
        }
        if ($page == 'add_ticket' or $page == "ticket_creation") {
            $this->handle_ticket_response();
        }
    }
}
