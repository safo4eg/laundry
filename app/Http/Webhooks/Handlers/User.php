<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\UserTrait;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;


class User extends WebhookHandler
{
    use UserTrait;

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

    public function start(): void
    {
        $flag = $this->data->get('start');

        if (!isset($flag)) {
            if (isset($this->user)) {
                $template_prefix_lang = $this->template_prefix.$this->user->language_code;
                $template_start = $template_prefix_lang.'.start';

                $buttons = [
                    'start' => $this->config['start']['start'][$this->user->language_code],
                    'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                ];
                $template = $template_prefix_lang.'.start';
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
                        $command = $this->message->text(); // получаем текст команды (как минимум /start)
                        if ($command === '/start') {
                            $this->chat
                                ->deleteMessage($this->user->message_id)
                                ->send(); // удаляем активное окно
                        }
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

                if($page === 'first_scenario' OR $page === 'second_scenario') {
                    $order->update([
                        'status_id' => 4,
                        'reason_id' => 5,
                        'last_step' => $this->user->step,
                        'active' => false
                    ]);

                    $this->user->update(['step' => null]);

                    $template_pause = $template_prefix_lang.'.order.pause';
                    $this->chat
                        ->message((string) view($template_pause))
                        ->send();
                }

                $response = $this->chat
                    ->message((string)view($template_start))
                    ->keyboard($keyboard)
                    ->send();

                $order->update([
                    'active' => false
                ]);

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

        if(isset($flag)) { // когда отправка с кнопки с флагом start
            $order = Order::where('user_id', $this->user->id)
                ->where('reason_id', 5)
                ->first();
            $step = 1;

            if(!isset($order)) {
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

            if($orders_amount === 0 OR $orders_amount === 1) {
                $scenario_num = 'first';
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

        if(isset($flag)) { // если прилетели данные именно при выборе языка (нажатие на кнопку выбора языка)
            $language_code = $this->data->get('language_code');

            $this->user->update([
                'language_code' => $language_code,
            ]);

            $this->start();
        }

        if(!isset($flag)) {
            $buttons = [
                'russia' => $this->config['select_language']['russia'],
                'english' => $this->config['select_language']['english']
            ];
            $template = $this->template_prefix.'select_language';
            $response = $this->chat->message((string) view($template))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make($buttons['english'])
                        ->action('select_language')
                        ->param('select_language', 1)
                        ->param('language_code', 'en'),
                    Button::make($buttons['russia'])
                        ->action('select_language')
                        ->param('select_language', 1)
                        ->param('language_code', 'ru')
                ]))->send();

            $this->user->update([
                'page' => 'select_language',
                'message_id' => $response->telegraphMessageId()
            ]);
        }
    }

    public function handle_scenario_request(): void
    {
        $page = $this->user->page; // получаем имя сценария
        $step = $this->user->step; // получаем шаг (может быть null)

        if($this->user->message_id) {
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
        } else if($page === 'second_scenario') {
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

        if($page === 'first_scenario') {
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
        } else if($page === 'second_scenario') {
            switch ($step) {
                case 1:
                    $this->geo_handler();
                    break;
                case 2:
                    $this->address_desc_handler();
                    break;
            }
        }
    }

    public function cancel_order(): void
    {
        $flag = $this->data->get('cancel_order');
        $language_code = $this->user->language_code;
        $template_prefix_lang = $this->template_prefix.$language_code;

        if($flag) {
            if($this->user->page === 'order_accepted') { // значит нажали пеервый раз
                $buttons = [
                    'check_bot' => $this->config['cancel_order']['check_bot'][$language_code],
                    'changed_my_mind' => $this->config['cancel_order']['changed_my_mind'][$language_code],
                    'quality' => $this->config['cancel_order']['quality'][$language_code],
                    'expensive' => $this->config['cancel_order']['expensive'][$language_code],
                    'back' => $this->config['cancel_order']['back'][$language_code]
                ];
                $template = $template_prefix_lang.'.order.cancel';

                $this->chat
                    ->edit($this->messageId)
                    ->message((string) view($template))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($buttons['check_bot'])
                                ->action('cancel_order')
                                ->param('cancel_order', 1)
                                ->param('choice', 1),
                            Button::make($buttons['changed_my_mind'])
                                ->action('cancel_order')
                                ->param('cancel_order', 1)
                                ->param('choice', 2),
                            Button::make($buttons['quality'])->action('cancel_order')
                                ->param('cancel_order', 1)
                                ->param('choice', 3),
                            Button::make($buttons['expensive'])->action('cancel_order')
                                ->param('cancel_order', 1)
                                ->param('choice', 4),
                            Button::make($buttons['back'])->action('open_to_previous_page')
                        ])->chunk(2))
                    ->send();

                $this->user->update([
                    'page' => 'cancel_order'
                ]);
            } else if($this->user->page === 'cancel_order') {
               // $choice = выбор причины отмены
                $choice = $this->data->get('choice');
                $order = $this->user->active_order;

                $buttons = [
                    'start' => $this->config['order_canceled']['start'][$language_code],
                    'recommend' => $this->config['order_canceled']['recommend'][$language_code]
                ];
                $template = $template_prefix_lang.'.order.canceled';
                $this->chat
                    ->edit($this->messageId)
                    ->message((string) view($template))
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

        if($flag) {
            $button = $this->config['order_wishes'][$this->user->language_code];
            $template = $this->template_prefix.$this->user->language_code.'.order.wishes';
            $response = $this->chat->edit($this->messageId)
                ->message((string) view($template, ['order_id' => $order->id]))
                ->keyboard(Keyboard::make()
                    ->button($button)
                    ->action('open_to_previous_page')
                    ->param('order_id', $order->id))
                ->send();

            $this->user->update([
                'page' => 'order_wishes',
                'step' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if(!$flag) {
            $wishes = $this->message->text();

            $order->update([
                'wishes' => $wishes
            ]);

            $this->open_to_previous_page();
        }
    }

    public function open_to_previous_page(): void
    {
        $order = $this->user->active_order;
        $message_id = $this->user->message_id;
        $page = $this->user->page;
        $template_prefix_lang = $this->template_prefix.$this->user->language_code;

        if($page === 'order_wishes' OR $page === 'cancel_order') {
            $buttons = [
                'wishes' => $this->config['order_accepted']['wishes'][$this->user->language_code],
                'cancel' => $this->config['order_accepted']['cancel'][$this->user->language_code],
                'recommend' => $this->config['order_accepted']['recommend'][$this->user->language_code],
            ];
            $template = $template_prefix_lang.'.order.accepted';
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons['wishes'])
                    ->action('write_order_wishes')
                    ->param('write_order_wishes', 1),
                Button::make($buttons['cancel'])
                    ->action('cancel_order')
                    ->param('cancel_order', 1),
                Button::make($buttons['recommend'])->action('ref')
            ]);

            $response = null;
            if($this->message) {
                $this->chat
                    ->deleteMessage($message_id)
                    ->send();
                $response = $this->chat
                    ->message((string) view($template, ['order_id' => $order->id]))
                    ->keyboard($keyboard)
                    ->send();
            }

            if($this->callbackQuery) {
                $response = $this->chat->edit($message_id)
                    ->message((string) view($template, ['order_id' => $order->id]))
                    ->keyboard($keyboard)
                    ->send();
            }

            $this->user->update([
                'page' => 'order_accepted',
                'step' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

    }

    protected function handleChatMessage(Stringable $text): void
    {
        $page = $this->user->page;
        if(isset($page)) {
            switch ($page) {
                case 'first_scenario':
                case 'second_scenario':
                    $this->handle_scenario_response();
                    break;
                case 'order_wishes':
                    $this->write_order_wishes();
                    break;
            }

        } // end if(isset($page))
    }
}
