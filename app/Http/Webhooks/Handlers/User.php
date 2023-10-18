<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\UserCommandsFuncsTrait;
use App\Http\Webhooks\Handlers\Traits\FirstAndSecondScenarioTrait;
use App\Models\Order;
use App\Models\OrderStatusPivot;
use App\Models\Referral;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;
use Illuminate\Database\Eloquent\Builder;
use App\Services\QR;


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

    public function referrals(): void
    {
        if($this->check_for_language_code()) return;
        $flag = $this->data->get('referrals');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        if(!isset($flag)) {
            $buttons_texts = [
                'recommend' => $this->config['referrals']['recommend'][$this->user->language_code],
                'new_order' => $this->config['referrals']['new_order'][$this->user->language_code],
                'continue_order' => $this->config['referrals']['continue_order'][$this->user->language_code],
                'info' => $this->config['referrals']['info'][$this->user->language_code]
            ];
            $template = $template_prefix_lang.'.referrals.main';

            $page = $this->user->page;
            $order = $this->user->active_order;
            if (
                $page === 'first_scenario' or
                $page === 'second_scenario' or
                $page === 'first_scenario_phone' or
                $page === 'first_scenario_whatsapp'

            ) {
                $this->terminate_filling_order($order);
            }

            if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
            {
                $this->delete_active_page();
            }

            $start_order_button = null;
            if($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                $start_order_button = Button::make($buttons_texts['continue_order'])
                    ->action('start')
                    ->param('start', 1);
            } else {
                $start_order_button = Button::make($buttons_texts['new_order'])->action('start');
            }

            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['recommend'])
                    ->url("https://t.me/share/url?url=https://t.me/rastan_telegraph_bot?start=ref{$this->user->id}"),
                Button::make($buttons_texts['info'])
                    ->action('referrals')
                    ->param('referrals', 1)
                    ->param('info', 1),
                $start_order_button
            ]);

            $response = null;
            if(isset($this->message)) {
                $response = $this->chat
                    ->photo(Storage::path("user/qr_code_{$this->user->id}.png"))
                    ->html(view($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat
                    ->photo(Storage::path("user/qr_code_{$this->user->id}.png"))
                    ->html(view($template, ['user' => $this->user]))
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'referrals',
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if(isset($flag)) {
            $info = $this->data->get('info');
            $back_button_text = $this->config['referrals']['back'][$this->user->language_code];
            $back_button = Button::make($back_button_text)->action('referrals');

            $template = $template_prefix_lang.'.referrals.info';
            if(isset($info)) {
                $referrals_amount = $this->user->referrals()->count();
                $bonuses = $this->user->referrals()->sum('bonuses');

                $this->chat
                    ->deleteMessage($this->user->message_id)
                    ->send();

                $response = $this->chat
                    ->message(view($template,
                        [
                            'referrals_amount' => $referrals_amount,
                            'bonuses' => $bonuses
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
        if($page === 'profile_change_phone_number') {
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
        if($this->check_for_language_code()) return;
        $flag = $this->data->get('profile');
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;

        if (!isset($flag)) {
            $buttons_texts = [
                'new_order' => $this->config['profile']['new_order'][$this->user->language_code],
                'continue_order' => $this->config['profile']['continue_order'][$this->user->language_code],
                'phone_number' => $this->config['profile']['phone_number'][$this->user->language_code],
                'whatsapp' => $this->config['profile']['whatsapp'][$this->user->language_code],
                'language' => $this->config['profile']['language'][$this->user->language_code],
            ];
            $template = $template_prefix_lang . '.profile.main';

            $page = $this->user->page;
            $order = $this->user->active_order;
            if (
                $page === 'first_scenario' or
                $page === 'second_scenario' or
                $page === 'first_scenario_phone' or
                $page === 'first_scenario_whatsapp'
            ) {
                $this->terminate_filling_order($order);
            }

            $start_order_button = null;
            if($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
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
            $recommend_button = Button::make($buttons['recommend'])->action('referrals');
            $back_button = Button::make($buttons['back'])
                ->action('orders')
                ->param('orders', 1); // нужно еще добавить choice чтобы знать с какого типа назад
            if ($status_id !== 4) {
                $buttons = [
                    'wishes' => $this->config['order_info']['wishes'][$this->user->language_code],
                    'cancel' => $this->config['order_info']['cancel'][$this->user->language_code],
                ];
                $back_button = $back_button->param('choice', 1);

                $keyboard = Keyboard::make()->buttons([
                    Button::make($buttons['wishes'])
                        ->action('write_order_wishes')
                        ->param('write_order_wishes', 1),
                    Button::make($buttons['cancel'])
                        ->action('cancel_order')
                        ->param('cancel_order', 1),
                    $recommend_button,
                    $back_button
                ]);

                if(isset($this->message)) {
                    $this->delete_active_page();
                    $response = $this->chat
                        ->message(view($template, [
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
                        ->message(view($template, [
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
        if($this->check_for_language_code()) return;
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
                $page = $this->user->page;
                $order = $this->user->active_order;

                if (
                    $page === 'first_scenario' or
                    $page === 'second_scenario' or
                    $page === 'first_scenario_phone' or
                    $page === 'first_scenario_whatsapp'
                ) {
                    $this->terminate_filling_order($order);
                }

                if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
                {
                    $this->delete_active_page();
                }
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
            if($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
                $start_order_button = Button::make($buttons_text['continue_order'])
                    ->action('start')
                    ->param('start', 1);
            } else {
                $start_order_button = Button::make($buttons_text['new_order'])->action('start');
            }

            if ($orders->isEmpty()) {
                $no_orders_template = $template_prefix_lang . '.orders.no_orders';
                $response = $this->chat
                    ->message(view($no_orders_template))
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
                        ->message(view($no_active_orders_template))
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
                    $view = (string)view($orders_template, ['orders' => $orders]);
                    $this->chat->edit($this->user->message_id)
                        ->message(preg_replace('#^[^\n]\s+#m', '', $view))
                        ->keyboard($keyboard)
                        ->send();
                }
            } else if ($choice == 2) { // завершенные заявки
                $this->chat->message('пока неизвестно какой статус будет у завершенного заказа')->send();
                return;
            }

        }

    }

    public function about(): void // можно попасть только с команды /about
    {
        if($this->check_for_language_code()) return;
        $template_prefix_lang = $this->template_prefix . $this->user->language_code;
        $page = $this->user->page;
        $order = $this->user->active_order;
        $buttons_text = [
            'new_order' => $this->config['about_us']['new_order'][$this->user->language_code],
            'continue_order' => $this->config['about_us']['continue_order'][$this->user->language_code],
        ];

        if(
            $page === 'first_scenario' or
            $page === 'second_scenario' or
            $page === 'first_scenario_phone' or
            $page === 'first_scenario_whatsapp'
        ) {
            $this->terminate_filling_order($order);
        }

        if (isset($this->user->message_id)) // если есть активное окно (окно с кнопками) - удаляем
        {
            $this->delete_active_page();
        }

        $start_order_button = null;
        if($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
            $start_order_button = Button::make($buttons_text['continue_order'])
                ->action('start')
                ->param('start', 1);
        } else {
            $start_order_button = Button::make($buttons_text['new_order'])->action('start');
        }

        $template_about = $template_prefix_lang . '.about_us';
        $response = $this->chat
            ->message((string)view($template_about))
            ->keyboard(Keyboard::make()->buttons([
                $start_order_button
            ]))
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

    public function start(string $ref = null): void
    {
        if($this->check_for_language_code()) return;
        $flag = $this->data->get('start');
        $ref_flag = false;
        if(isset($ref) and $ref !== '/start') $ref_flag = true;

        if (!isset($flag)) {
            if (isset($this->user)) {
                $template_prefix_lang = $this->template_prefix . $this->user->language_code;
                $template_start = $template_prefix_lang . '.start';

                $buttons_text = [
                    'new_order' => $this->config['start']['new_order'][$this->user->language_code],
                    'continue_order' => $this->config['start']['continue_order'][$this->user->language_code],
                    'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                ];

                $page = $this->user->page;
                $order = $this->user->active_order;

                if(
                    $page === 'first_scenario' or
                    $page === 'second_scenario' or
                    $page === 'first_scenario_phone' or
                    $page === 'first_scenario_whatsapp'
                ) {
                    $this->terminate_filling_order($order);
                }

                $start_order_button = null;
                if($this->check_for_incomplete_order()) { // проверка есть ли недозаполненный заказ
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

                QR::generate_referrals_qr($this->user);

                if($ref_flag) {
                    $inviter_id = trim(str_replace('ref', '', $ref));
                    $invited_id = $this->user->id;

                    Referral::create([
                        'inviter_id' => $inviter_id,
                        'invited_id' => $invited_id
                    ]);
                }

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
            if($orders_amount === 1) {
                switch ($step) {
                    case 1:
                    case 2:
                        if($phone_number and $whatsapp) $scenario = 'second_scenario';
                        else if ($phone_number and !$whatsapp) $scenario = 'first_scenario_whatsapp';
                        else if(!$phone_number and $whatsapp) $scenario = 'first_scenario_phone';
                        else if(!$phone_number and !$whatsapp) $scenario = 'first_scenario';
                        break;
                    case 3:
                    case 4:
                    case 5:
                        if($phone_number and $whatsapp) {
                            $scenario = 'first_scenario';
                            $step = 5;
                            break;
                        }
                        else if($phone_number and !$whatsapp) {
                            $scenario = 'first_scenario_whatsapp';
                        }
                        else if(!$phone_number and $whatsapp) {
                            $scenario = 'first_scenario_phone';
                        }
                        else if(!$phone_number and !$whatsapp) {
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
                        if($phone_number) $scenario = 'second_scenario';
                        else if(!$phone_number) $scenario = 'first_scenario_phone';
                        break;
                    case 4:
                        if($phone_number) $scenario = 'first_scenario_phone';
                        else if(!$phone_number) {
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
                    ->message((string)view($template))
                    ->keyboard($keyboard)
                    ->send();

            } else if(isset($page) and $page === 'select_language') {
                $keyboard = Keyboard::make()->buttons([
                    $button_select_en->param('page', 1),
                    $button_select_ru->param('page', 1),
                ]);

                $this->delete_active_page();

                $response = $this->chat
                    ->message((string)view($template))
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

        if($page === 'first_scenario') {
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
        } else if($page === 'first_scenario_phone' or $page === 'first_scenario_whatsapp') {
            $steps_amount = 4;
            switch ($step) {
                case 1:
                    $this->request_geo($step, $steps_amount);
                    break;
                case 2:
                    $this->request_address_desc($step, $steps_amount);
                    break;
                case 3:
                    if($page === 'first_scenario_phone') $this->request_contact($step, $steps_amount);
                    else if($page === 'first_scenario_whatsapp') $this->request_whatsapp($step, $steps_amount);
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

        } else if($page === 'first_scenario_phone' or $page === 'first_scenario_whatsapp') {
            $steps_amount = 4;
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
                case 3:
                    if($page === 'first_scenario_phone') $this->contact_handler();
                    else if($page === 'first_scenario_whatsapp') $this->whatsapp_handler();
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

            $response = $this->chat->edit($this->user->message_id)
                ->message(view($template, ['order_id' => $order->id]))
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

    use Traits\SupportTrait;

    public function support()
    {
        if($this->check_for_language_code()) return;
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
                case 'support':
                    $this->support();
                    break;
            }
        } // end if(isset($page))
    }
}
