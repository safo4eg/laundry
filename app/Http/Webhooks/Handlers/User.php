<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\UserTrait;
use App\Models\Order;
use App\Models\User as UserModel;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
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

        if (empty($flag)) {
            // заходит сюда с кнопки "заказать стирку" тк там не указан param()
            if ($this->user) {
                $page = $this->user->page;
                if ($page === 'menu' OR $page === 'order_canceled') {

                    $buttons = [
                        'start' => $this->config['start']['start'][$this->user->language_code],
                        'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                    ];
                    $template = $this->template_prefix.$this->user->language_code.'.start';
                    $keyboard = Keyboard::make()->buttons([
                        Button::make($buttons['start'])
                            ->action('start')
                            ->param('start', 1)
                            ->param('choice', 1),
                        Button::make($buttons['reviews'])->url('https://t.me/laundrybot_feedback')

                    ]);

                    if(isset($this->user->message_id)) {
                        // если активное окно есть - редактируем ,
                        // если нет то отправляем просто новое сообщение

                        if(isset($this->message)) { // проверяем проинициализирована ли переменная, т.к сюда можно попасть и с кнопки
                            $command = $this->message->text(); // получаем текст команды (как минимум /start)
                            if($command === '/start') {
                                $this->chat
                                    ->deleteMessage($this->user->message_id)
                                    ->send(); // удаляем активное окно
                            }
                        } else { // если не с команды попало, тогда редактируем, т.к ничего не писали
                            $this->chat
                                ->edit($this->user->message_id)
                                ->message((string) view($template))
                                ->keyboard($keyboard)
                                ->send();
                            return;
                        }
                    }

                    $this->chat
                        ->message((string) view($template))
                        ->keyboard($keyboard)
                        ->send();
                    return;
                } else return;
            } else {
                $chat_id = $this->message->from()->id();
                $username = $this->message->from()->username();

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

                $this->user = UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                    'page' => 'select_language',
                    'message_id' => $response->telegraphMessageId()
                ]);
            }
        } else if(!empty($flag)) { // когда отправка с кнопки с флагом start

            Order::create([
                'user_id' => $this->user->id,
                'status_id' => 1,
                'active' => true
            ]);

            $scenario_num = null;

            if ($this->user->phone_number) {
                $scenario_num = 'second';
            } else {
                $scenario_num = 'first';
            }

            $this->user->update([
                'page' => "{$scenario_num}_scenario",
                'step' => 1
            ]);

            $this->handle_scenario_request();
        }

    }

    public function select_language(): void
    {
        $flag = $this->data->get('select_language');

        if($flag) { // если прилетели данные именно при выборе языка (нажатие на кнопку выбора языка)
            $language_code = $this->data->get('language_code');

            if (!empty($language_code)) {
                $this->user->update([
                    'language_code' => $language_code,
                    'page' => 'menu'
                ]);
                $this->start();
            }
        }
    }

    public function handle_scenario_request(): void
    {
        $page = $this->user->page; // получаем имя сценария
        $step = $this->user->step; // получаем шаг (может быть null)

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
            } //end switch

        } // end if
    }
}
