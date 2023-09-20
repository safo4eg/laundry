<?php

namespace App\Http\Webhooks\Handlers;

use App\Models\Order;
use App\Models\User as UserModel;
use App\Services\Geo;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;


class User extends WebhookHandler
{

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
        $choice = $this->data->get('choice');

        if (empty($choice)) {

            if ($this->user) {
                if ($this->user->page === 'menu') {
                    $buttons = [
                        'start' => $this->config['start']['start'][$this->user->language_code],
                        'reviews' => $this->config['start']['reviews'][$this->user->language_code],
                    ];
                    $template = $this->template_prefix.$this->user->language_code.'.start';

                    $this->chat->message((string) view($template))
                        ->keyboard(Keyboard::make()->buttons([
                            Button::make($buttons['start'])->action('start')->param('choice', 1),
                            Button::make($buttons['reviews'])->url('https://t.me/laundrybot_feedback')
                        ]))->send();
                    return;
                } else return;
            } else {
                $chat_id = $this->message->from()->id();
                $username = $this->message->from()->username();

                $this->user = UserModel::create([
                    'chat_id' => $chat_id,
                    'username' => $username,
                    'page' => 'select_language'
                ]);

                $buttons = [
                    'russia' => $this->config['select_language']['russia'],
                    'english' => $this->config['select_language']['english']
                ];
                $template = $this->template_prefix.'select_language';
                $this->chat->message((string) view($template))
                    ->keyboard(Keyboard::make()->buttons([
                        Button::make($buttons['english'])->action('select_language')->param('choice', 'en'),
                        Button::make($buttons['russia'])->action('select_language')->param('choice', 'ru')
                    ]))->send();
            }
        }

        if (!empty($choice)) {
            Order::create([
                'user_id' => $this->user->id,
                'status_id' => 1,
                'active' => true
            ]);

            $scenarios = json_decode(Storage::get('scenarios'), true);
            $scenario_num = null;

            if ($this->user->phone_number) {
                $scenario_num = 'second';
                $scenario = $scenarios[$scenario_num][1];
                // если телефонный номер имеется значит на другой сценарий
            } else {
                $scenario_num = 'first';
                $scenario = $scenarios[$scenario_num];

                $button = $this->config['send_location'][$this->user->language_code];
                $template = $this->template_prefix.$this->user->language_code.$scenario[1]['template'];
                $this->chat->deleteMessage($this->messageId)->send();
                $this->chat->message((string) view(
                    $template,
                    [
                        'step_id' => $scenario[1]['step_id'],
                        'steps_amount' => count($scenario)
                    ]))
                    ->replyKeyboard(ReplyKeyboard::make()
                    ->button($button)->requestLocation())
                    ->send();
            }

            $this->user->update([
                'page' => "{$scenario_num}_scenario",
                'step_id' => 1
            ]);
        }

    }

    public function select_language(): void
    {
        $language_code = $this->data->get('choice');

        if (!empty($language_code)) {
            $this->user->update(['language_code' => $language_code, 'page' => 'start']);

            $buttons = [
                'start' => $this->config['start']['start'][$this->user->language_code],
                'reviews' => $this->config['start']['reviews'][$this->user->language_code],
            ];
            $template = $this->template_prefix.$this->user->language_code.'.start';
            $this->chat->edit($this->messageId)
                ->message((string) view($template))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make($buttons['start'])->action('start')->param('choice', 1),
                    Button::make($buttons['reviews'])->url('https://t.me/laundrybot_feedback')
                ]))->send();
        }
    }


    public function first_scenario(): void
    {
        $scenario = json_decode(Storage::get('scenarios'), true)['first'];
        $step_id = $this->user->step_id;

        $template_prefix_lang = $this->template_prefix.$this->user->language_code;
        $order = $this->user->active_order;

        if ($step_id === 1) {
            $location = $this->message->location();
            if ($location) {
                $y = $location->latitude();
                $x = $location->longitude();
                $geo = new Geo($x, $y);

                $order->update([
                    'geo' => "$x,$y",
                    'address' => $geo->address
                ]);

                $this->user->update([
                    'step_id' => ++$step_id
                ]);

                $this->chat->message((string) view(
                    $template_prefix_lang.$scenario[$step_id]['template'],
                    [
                        'step_id' => $scenario[$step_id]['step_id'],
                        'steps_amount' => count($scenario)
                    ]
                ))->removeReplyKeyboard()->send();
            }
        } else if ($step_id === 2) {
            $address_desc = $this->message->text();

            $order->update([
                'address_desc' => $address_desc
            ]);

            $this->user->update([
                'step_id' => ++$step_id
            ]);

            $button = $this->config['send_contact'][$this->user->language_code];
            $template = $template_prefix_lang.$scenario[$step_id]['template'];
            $this->chat->message((string) view(
                $template,
                ['step_id' => $step_id, 'steps_amount' => count($scenario)]
            ))
                ->replyKeyboard(ReplyKeyboard::make()
                    ->button($button)->requestContact())
                ->send();
        } else if ($step_id === 3) {
            $contact = $this->message->contact();
            if($contact) {
                $phone_number = $contact->phoneNumber();
                $this->user->update([
                    'phone_number' => $phone_number,
                    'step_id' => ++$step_id
                ]);

                $this->chat->message((string) view(
                    $template_prefix_lang.$scenario[$step_id]['template'],
                    [
                        'step_id' => $step_id,
                        'steps_amount' => count($scenario)
                    ]))
                    ->removeReplyKeyboard()
                    ->send();
            }
        } else if ($step_id === 4) {
            $whatsapp_number = $this->message->text();

            if(mb_strlen($whatsapp_number) >= 32) {
                $whatsapp_number = null;
            } else {
                $whatsapp_number = ((int) $whatsapp_number)? $whatsapp_number: null;
            }

            $this->user->update([
               'whatsapp' => $whatsapp_number,
                'step_id' => ++$step_id
            ]);

            $button = $this->config['accept_order'][$this->user->language_code];
            $template = $template_prefix_lang.$scenario[$step_id]['template'];
            $this->chat
                ->message((string) view($template, [
                'step_id' => $step_id,
                'steps_amount' => count($scenario)]))
                ->keyboard(Keyboard::make()
                    ->button($button)->action('first_scenario')->param('choice', 1))
                ->send();
        } else if ($step_id === 5) {
            $choice = $this->data->get('choice');

            if($choice) {
                $order->update([ // в этот момент заказ улетает в ADMIN CHAT
                    'status_id' => 2
                ]);

                $buttons = [
                    'wishes' => $this->config['order_accepted']['wishes'][$this->user->language_code],
                    'cancel' => $this->config['order_accepted']['cancel'][$this->user->language_code],
                    'recommend' => $this->config['order_accepted']['recommend'][$this->user->language_code],
                ];
                $template = $template_prefix_lang.'.order.accepted';
                $response = $this->chat->edit($this->messageId)
                    ->message((string) view($template, [
                        'order_id' => $order->id
                    ]))
                    ->keyboard(Keyboard::make()
                        ->buttons([
                            Button::make($buttons['wishes'])
                                ->action('write_order_wishes')
                                ->param('callback_data', 1),
                            Button::make($buttons['cancel'])
                                ->action('cancel_order')
                                ->param('callback_data', 1),
                            Button::make($buttons['recommend'])->action('ref')
                        ]))
                    ->send();

                $this->user->update([
                    'page' => 'order_accepted',
                    'step_id' => null,
                    'message_id' => $response->telegraphMessageId()
                ]);
            }
        }
    }

    public function write_order_wishes(): void
    {
        $callback_data = $this->data->get('callback_data');
        $order = $this->user->active_order;

        if($callback_data) {
            Log::debug($this->messageId);
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
                'step_id' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

        if(!$callback_data) {
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

        if($page === 'order_wishes') {
            $buttons = [
                'wishes' => $this->config['order_accepted']['wishes'][$this->user->language_code],
                'cancel' => $this->config['order_accepted']['cancel'][$this->user->language_code],
                'recommend' => $this->config['order_accepted']['recommend'][$this->user->language_code],
            ];
            $template = $template_prefix_lang.'.order.accepted';
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons['wishes'])
                    ->action('write_order_wishes')
                    ->param('callback_data', 1),
                Button::make($buttons['cancel'])
                    ->action('cancel_order')
                    ->param('callback_data', 1),
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
                'step_id' => null,
                'message_id' => $response->telegraphMessageId()
            ]);
        }

    }

    protected function handleChatMessage(Stringable $text): void
    {
        if(isset($this->user->page)) {
            switch ($this->user->page) {
                case 'first_scenario':
                    $this->first_scenario();
                    break;
                case 'order_wishes':
                    $this->write_order_wishes();
                    break;
            }
        }
    }
}
