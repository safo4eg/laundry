<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\CommandsFuncsTrait;
use App\Http\Webhooks\Handlers\Traits\FirstAndSecondScenarioTrait;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;


class User extends WebhookHandler
{
    use FirstAndSecondScenarioTrait, CommandsFuncsTrait;

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

    public function profile_change_handler(): void
    {
        $page = $this->user->page;
        Log::debug('зашел в метод');
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
        $flag = $this->data->get('profile');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        if (!isset($flag)) {
            $buttons_texts = [
                'phone_number' => $this->config['profile']['phone_number'][$this->user->language_code],
                'whatsapp' => $this->config['profile']['whatsapp'][$this->user->language_code],
                'language' => $this->config['profile']['language'][$this->user->language_code],
            ];
            $template = $template_prefix_lang . '.profile.main';
            $keyboard = Keyboard::make()
                ->button($buttons_texts['phone_number'])
                ->action('profile')
                ->param('profile', 1)
                ->param('choice', 1)
                ->button($buttons_texts['whatsapp'])
                ->action('profile')
                ->param('profile', 1)
                ->param('choice', 2)
                ->button($buttons_texts['language'])
                ->action('profile')
                ->param('profile', 1)
                ->param('choice', 3);

            $response = null;
            if (isset($this->message)) {
                $page = $this->user->page;
                $order = $this->user->active_order;

                if ($page === 'first_scenario' or $page === 'second_scenario') {
                    $this->terminate_filling_order($order);
                }

                if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
                {
                    $this->delete_active_page();
                }

                $response = $this->chat
                    ->message(view($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat
                    ->edit($this->user->message_id)
                    ->message(view($template, ['user' => $this->user]))
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
                    ->message(view($template))
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

            $buttons = [
                'recommend' => $this->config['order_info']['recommend'][$this->user->language_code],
                'back' => $this->config['order_info']['back'][$this->user->language_code]
            ];
            $recommend_button = Button::make($buttons['recommend'])->action('ref');
            $back_button = Button::make($buttons['back'])
                ->action('orders')
                ->param('orders', 1); // нужно еще добавить choice чтобы знать с какого типа назад
            if ($status_id === 2 or $status_id === 3) {
                $buttons = [
                    'wishes' => $this->config['order_info']['wishes'][$this->user->language_code],
                    'cancel' => $this->config['order_info']['cancel'][$this->user->language_code],
                ];
                $back_button = $back_button->param('choice', 1);

                $this->chat->edit($this->user->message_id)
                    ->message(view($template, [
                        'order' => $order,
                        'status_1' => $status_1
                    ]))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($buttons['wishes'])
                                ->action('write_order_wishes')
                                ->param('write_order_wishes', 1),
                            Button::make($buttons['cancel'])
                                ->action('cancel_order')
                                ->param('cancel_order', 1),
                            $recommend_button,
                            $back_button
                        ])
                    )->send();

                $order->update([
                    'active' => true
                ]);
            } else if ($status_id === 4 and $order->reason_id !== 5) {
                $back_button = $back_button->param('choice', 2);

                $this->chat->edit($this->user->message_id)
                    ->message(view($template, [
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
        $flag = $this->data->get('orders');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        $buttons_text = [
            'order_laundry' => $this->config['orders']['order_laundry'][$this->user->language_code],
            'active' => $this->config['orders']['active'][$this->user->language_code],
            'canceled' => $this->config['orders']['canceled'][$this->user->language_code],
            'completed' => $this->config['orders']['completed'][$this->user->language_code],
            'back' => $this->config['orders']['back'][$this->user->language_code]
        ];

        $start_button = Button::make($buttons_text['order_laundry'])
            ->action('start')
            ->param('start', 1);

        if (!isset($flag)) {
            $keyboard = Keyboard::make()->buttons([$start_button]);

            if (isset($this->message)) {
                $page = $this->user->page;
                $order = $this->user->active_order;

                if ($page === 'first_scenario' or $page === 'second_scenario') {
                    $this->terminate_filling_order($order);
                }

                if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
                {
                    $this->delete_active_page();
                }
            }

            $orders = Order::where('user_id', $this->user->id)
                ->orderBy(OrderStatus::select('created_at')
                    ->whereColumn('order_id', 'orders.id')
                    ->where('status_id', 1)
                    ->limit(1)
                    , 'desc')->get();


            $response = null;

            if ($orders->isEmpty()) {
                $no_orders_template = $template_prefix_lang . '.orders.no_orders';
                $response = $this->chat
                    ->message(view($no_orders_template))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $keyboard = $keyboard
                    ->button($buttons_text['active'])
                    ->action('orders')
                    ->param('orders', 1)
                    ->param('choice', 1)
                    ->button($buttons_text['canceled'])
                    ->action('orders')
                    ->param('orders', 1)
                    ->param('choice', 2)
                    ->width(0.5)
                    ->button($buttons_text['completed'])
                    ->action('orders')
                    ->param('orders', 1)
                    ->param('choice', 3)
                    ->width(0.5);


                $orders_template = $template_prefix_lang . '.orders.all';
                $view = (string)view($orders_template, ['orders' => $orders]);

                if (isset($this->message)) {
                    $response = $this->chat
                        ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                        ->keyboard($keyboard)
                        ->send();
                } else if (isset($this->callbackQuery)) {
                    $this->chat
                        ->edit($this->user->message_id)
                        ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                        ->keyboard($keyboard)
                        ->send();
                    return;
                }
            }

            if (isset($order)) {
                $order->update([
                    'active' => false
                ]);
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
                    ->whereBetween('status_id', [1, 3])
                    ->orWhere(function (Builder $query) {
                        $query
                            ->where('status_id', 4)
                            ->where('reason_id', 5);
                    })
                    ->orderBy(OrderStatus::select('created_at')
                        ->whereColumn('order_id', 'orders.id')
                        ->where('status_id', 1)
                        ->limit(1)
                        , 'desc')
                    ->get();

                if ($orders->isEmpty()) {
                    $no_active_orders_template = $template_prefix_lang . '.orders.no_active_orders';
                    $this->chat->edit($this->user->message_id)
                        ->message(view($no_active_orders_template))
                        ->keyboard(Keyboard::make()->row([$start_button, $back_button]))->send();
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
                    $keyboard->row([$start_button, $back_button]);

                    $orders_template = $template_prefix_lang . '.orders.active';
                    $view = (string)view($orders_template, ['orders' => $orders]);
                    $this->chat->edit($this->user->message_id)
                        ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                        ->keyboard($keyboard)
                        ->send();
                }
            } else if ($choice == 2) {
                $orders = Order::where('user_id', $this->user->id)
                    ->where('status_id', 4)
                    ->whereNot('reason_id', 5)
                    ->orderBy(OrderStatus::select('created_at')
                        ->whereColumn('order_id', 'orders.id')
                        ->where('status_id', 1)
                        ->limit(1)
                        , 'desc')
                    ->get();

                if ($orders->isEmpty()) {
                    $no_canceled_orders_template = $template_prefix_lang . '.orders.no_canceled_orders';
                    $this->chat->edit($this->user->message_id)
                        ->message(view($no_canceled_orders_template))
                        ->keyboard(Keyboard::make()->row([$start_button, $back_button]))
                        ->send();
                } else {
                    $orders_buttons_line = [];
                    foreach ($orders as $order) {
                        $orders_buttons_line[] = Button::make("#{$order->id}")
                            ->action('show_order_info')
                            ->param('show_order_info', 1)
                            ->param('id', $order->id);
                    }
                    $orders_buttons_slices = collect($orders_buttons_line)->chunk(2)->toArray();
                    $keyboard = Keyboard::make();
                    foreach ($orders_buttons_slices as $orders_buttons) {
                        $keyboard = $keyboard->row($orders_buttons);
                    }
                    $keyboard->row([$start_button, $back_button]);

                    $orders_template = $template_prefix_lang . '.orders.canceled';
                    $view = (string)view($orders_template, ['orders' => $orders]);
                    $this->chat->edit($this->user->message_id)
                        ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                        ->keyboard($keyboard)->send();
                }
            } else if ($choice == 3) { // показ завершенных заказов
                $this->chat->message('пока неизвестно какой статус будет у завершенного заказа')->send();
                return;
            }

        }

    }

    public function about(): void // можно попасть только с команды /about
    {
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;
        $page = $this->user->page;
        $order = $this->user->active_order;

        if ($page === 'first_scenario' or $page === 'second_scenario') {
            $this->terminate_filling_order($order);
        }

        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        $button = $this->config['about_us'][$this->user->language_code];
        $template_about = $template_prefix_lang . '.about_us';
        $response = $this->chat
            ->message((string)view($template_about))
            ->keyboard(Keyboard::make()->button($button)->action('start')->param('start', 1))
            ->send();

        if (isset($order)) {
            $order->update([
                'active' => false
            ]);
        }

        $this->user->update([
            'page' => 'about_us',
            'message_id' => $response->telegraphMessageId()
        ]);
    }

    public function start(): void
    {
        $flag = $this->data->get('start');

        if (!isset($flag)) {
            if (isset($this->user)) {
                $template_prefix_lang = $this->template_prefix . $this->user->language_code;
                $template_start = $template_prefix_lang . '.start';

                $buttons = [
                    'start' => $this->config['start']['start'][$this->user->language_code],
                    'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                ];
                $keyboard = Keyboard::make()->buttons([
                    Button::make($buttons['start'])
                        ->action('start')
                        ->param('start', 1)
                        ->param('choice', 1),
                    Button::make($buttons['reviews'])->url('https://t.me/laundrybot_feedback')
                ]);

                if (isset($this->user->message_id)) {
                    // если активное окно есть - редактируем ,
                    // если нет то отправляем просто новое сообщение

                    if (isset($this->message)) { // проверяем проинициализирована ли переменная, т.к сюда можно попасть и с кнопки
                        $this->delete_active_page();
                    } else { // если не с команды попало, тогда редактируем, т.к ничего не писали
                        $this->chat
                            ->edit($this->user->message_id)
                            ->message((string)view($template_start))
                            ->keyboard($keyboard)
                            ->send();

                        $this->user->update([
                            'page' => 'start'
                        ]);
                        return;
                    }
                }

                $page = $this->user->page;
                $order = $this->user->active_order;

                if ($page === 'first_scenario' or $page === 'second_scenario') {
                    $this->terminate_filling_order($order);
                }

                $response = $this->chat
                    ->message((string)view($template_start))
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

            } else {
                $chat_id = $this->message->from()->id();
                $username = $this->message->from()->username();

                $this->user = UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                ]);

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
                    OrderStatus::where('order_id', $order->id)
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

            $scenario_num = null;


            $orders_amount = Order::where('user_id', $this->user->id)->count();

            if ($orders_amount === 1) {
                $scenario_num = 'first';
                if (isset($this->user->phone_number) and isset($this->user->whatsapp)) {
                    $scenario_num = 'second';
                }
            } else {
                $scenario_num = 'second';
            }

            $this->user->update([
                'page' => "{$scenario_num}_scenario",
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

            if (isset($page)) {
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
                    ->message((string)view($template))
                    ->keyboard($keyboard)
                    ->send();

            } else {
                $keyboard = Keyboard::make()->buttons([
                    $button_select_en->param('page', 1),
                    $button_select_ru->param('page', 1),
                ]);

                $response = $this->chat
                    ->message((string)view($template))
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
            $phone_number = $this->user->phone_number;
            $whatsapp = $this->user->whatsapp;

            $steps_amount = (isset($phone_number) or isset($whatsapp)) ? 4 : 5;

            switch ($step) {
                case 1:
                    $this->request_geo($step, $steps_amount);
                    break;
                case 2:
                    $this->request_address_desc($step, $steps_amount);
                    break;
                case 3:
                    if (isset($phone_number)) $this->request_whatsapp();
                    else $this->request_contact($step, $steps_amount);
                    break;
                case 4:
                    if ($steps_amount === 4) $this->request_accepted_order();
                    else if ($steps_amount === 5) $this->request_whatsapp($step, $steps_amount);
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
        } else if ($page === 'third_scenario' or $page === 'fourth_scenario') {
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
                case 3:
                    if ($page === 'third_scenario') $this->whatsapp_handler();
                    if ($page === 'fourth_scenario') $this->contact_handler();
            }
        }
    }

    public function cancel_order(): void
    {
        $flag = $this->data->get('cancel_order');
        $language_code = $this->user->language_code;
        $template_prefix_lang = $this->template_prefix . $language_code;

        if ($flag) {
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
                    ->message(view($template))
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
                    ->message((string)view($template))
                    ->keyboard(Keyboard::make()
                        ->button($buttons['start'])->action('start')
                        ->button($buttons['recommend'])->action('ref')->param('choice', 2)
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
            if ($this->user->page === 'orders') {
                $keyboard = $keyboard->button($button)
                    ->action('show_order_info')
                    ->param('show_order_info', 1)
                    ->param('id', $order->id);
            }

            if ($this->user->page === 'order_accepted') {
                $keyboard = $keyboard->button($button)
                    ->action('order_accepted_handler')
                    ->param('order_accepted_handler', 1);
            }

            $response = $this->chat->edit($this->user->message_id)
                ->message(view($template, ['order_id' => $order->id]))
                ->keyboard($keyboard)
                ->send();

            $this->user->update([
                'page' => 'order_wishes',
                'step' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if (!$flag) {
            $wishes = $this->message->text();

            $order->update([
                'wishes' => $wishes
            ]);

            $this->orders();
        }
    }

    use Traits\SupportTrait;

    public function support()
    {
        $step = $this->user->step;
        $flag = $this->data->get("support");



        if(isset($flag) or $step === 2) {
            switch ($step) {
                case 1:
                    $this->create_ticket();
                    break;
                case 2:
                    $this->ticket_created();
            }
        } else if(!isset($flag)) {

            if(isset($this->message)) {
                $page = $this->user->page;
                $order = $this->user->active_order;

                if ($page === 'first_scenario' or $page === 'second_scenario') {
                    $this->terminate_filling_order($order);
                }

                if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
                {
                    $this->delete_active_page();
                }
            }

            $this->support_start();
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $page = $this->user->page;
        if (isset($page)) {
            switch ($page) {
                case 'first_scenario':
                case 'second_scenario':
                    $this->handle_scenario_response();
                    break;
                case 'order_wishes':
                    $this->write_order_wishes();
                    break;
                case 'profile_change_phone_number':
                case 'profile_change_whatsapp':
                    $this->profile_change_handler();
                    break;
                case 'support':
                    $this->support();
                    break;
            }
        } // end if(isset($page))
    }
}
