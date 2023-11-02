<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\User as UserModel;
use App\Models\ChatOrderPivot;
use App\Models\File;
use App\Services\FakeRequest;
use App\Services\Helper;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\Order;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;

class Admin extends WebhookHandler
{
    use ChatsHelperTrait;
    public function __construct()
    {
        $this->buttons = config('buttons.admin');
        $this->general_buttons = config('buttons.chats');
        $this->template_prefix = 'bot.chats.';
        parent::__construct();
    }

    public function bonuses(): void
    {
        $flag = $this->data->get('bonuses');

        if(isset($flag)) {
            $user = $this->data->get('user'); // запрос юзер_ид

            if(isset($user)) {
                $this->delete_message_by_types([16]);
                $template = "Enter the user ID in order to find out its balance";
                $cancel_button = Button::make('Cancel')
                    ->action('commands');
                $keyboard = Keyboard::make()->buttons([$cancel_button]);
                $response = $this->chat
                    ->message($template)
                    ->keyboard($keyboard)
                    ->send();

                ChatOrderPivot::create([
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => null,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 21
                ]);
            }
        }

        if(!isset($flag)) {
            $this->delete_message_by_types([3, 12, 21]);
            $user_id = $this->data->get('user_id');
            $user = UserModel::where('id', $user_id)->first();

            $template = $this->template_prefix.'user_balance';
            $buttons_texts = $this->buttons['bonuses'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['plus'])
                    ->action('bonuses')
                    ->param('bonuses', 1)
                    ->param('plus', 1)
                    ->param('user_id', $user->id),

                Button::make($buttons_texts['minus'])
                    ->action('bonuses')
                    ->param('bonuses', 1)
                    ->param('minus', 1)
                    ->param('user_id', $user->id),

                Button::make($buttons_texts['back'])
                    ->action('commands')
            ]);

            $response = $this->chat
                ->message(view($template, ['user' => $user]))
                ->keyboard($keyboard)
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'user_id' => $user->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 22
            ]);
        }
    }

    public function notification(): void
    {
        $flag = $this->data->get('notification');
        $buttons_texts = $this->buttons['create_notification'];

        if(isset($flag)) {
            $notification = $this->chat->storage()->get('notification');
            $text = $this->data->get('text');
            $button = $this->data->get('button');
            $preview = $this->data->get('preview');
            $send = $this->data->get('send');

            if(isset($text)) {
                if(isset($notification["text_{$text}"])) {
                    unset($notification["text_{$text}"]);
                    $this->chat->storage()->set('notification', $notification);
                    $fake_dataset = [
                        'action' => 'notification',
                        'params' => []
                    ];
                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                    (new self())->handle($fake_request, $this->bot);
                } else {
                    $this->delete_message_by_types([16, 17, 18, 19]);

                    $template = "";
                    $message_type_id = null;
                    if($text === 'ru') {
                        $template = '📝Напишите текст уведомления на русском языке';
                        $message_type_id = 18;
                    }
                    if($text === 'en') {
                        $template = '📝Write the notification text in English';
                        $message_type_id = 19;
                    }

                    $keyboard = Keyboard::make()->buttons([
                        Button::make('Cancel')
                            ->action('delete_message_by_types')
                            ->param('delete', 1)
                            ->param('type_id', '18')
                    ]);

                    $response = $this->chat
                        ->message($template)
                        ->keyboard($keyboard)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => null,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => $message_type_id
                    ]);
                }
            }

            if(isset($button)) {
                $text = '';
                if($button === 'start') $text = 'start';
                else if($button === 'recommend') $text = 'recommend';

                if(isset($notification['buttons'][$text])) unset($notification['buttons'][$text]);
                else $notification['buttons'][$text] = 1;

                $this->chat->storage()->set('notification', $notification);
                $fake_dataset = [
                    'action' => 'notification',
                    'params' => []
                ];
                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                (new self())->handle($fake_request, $this->bot);
            }

            if(isset($preview)) {
                $this->delete_message_by_types([20]);
                $notification = $this->chat->storage()->get('notification');

                $language = $this->data->get('lang');
                if(isset($language)) {
                    $buttons = [];
                    $template = $notification["text_{$language}"];
                    foreach ($notification['buttons'] as $key => $value) {
                        $buttons[] = Button::make($buttons_texts[$key])
                            ->action('test_act');
                    }

                    if($language === 'en') {
                        $buttons[] = Button::make($buttons_texts['ru'])
                            ->action('notification')
                            ->param('notification', 1)
                            ->param('preview', 1)
                            ->param('lang', 'ru');
                    }

                    if ($language === 'ru') {
                        $buttons[] = Button::make($buttons_texts['en'])
                            ->action('notification')
                            ->param('notification', 1)
                            ->param('preview', 1)
                            ->param('lang', 'en');
                    }

                    $buttons[] = Button::make($buttons_texts['send'])
                        ->action('notification')
                        ->param('notification', 1)
                        ->param('send', 1);

                    $buttons[] = Button::make($buttons_texts['cancel'])
                        ->action('notification');

                    $keyboard = Keyboard::make()->buttons($buttons);
                    $this->delete_message_by_types([16, 17, 18, 19]);
                    $response = $this->chat
                        ->message($template)
                        ->keyboard($keyboard)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => null,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 20
                    ]);
                }
            }

            if(isset($send)) {
                $notification = $this->chat->storage()->get('notification');

                $users = UserModel::all();
                foreach ($users as $user) {
                    $user_lang_code = $user->language_code;
                    $template = $notification["text_{$user_lang_code}"];
                    $buttons = [];
                    foreach ($notification['buttons'] as $key => $button) {
                        if($key === 'start') {
                            $button_texts = [
                                'ru' => 'Заказать стирку',
                                'en' => 'Order laundry'
                            ];
                            $buttons[] = Button::make($button_texts[$user_lang_code])
                                ->action('start');
                        } else if($key === 'recommend') {
                            $button_texts = [
                                'ru' => 'Рекомендовать друзьям',
                                'en' => 'Recommend to friends'
                            ];
                            $ref_link = "https://t.me/share/url?url=https://t.me/rastan_telegraph_bot?start=ref{$user->id}";
                            $buttons[] = Button::make($button_texts[$user_lang_code])
                                ->url($ref_link);
                        }

                        $keyboard = Keyboard::make()->buttons($buttons);
                        Helper::send_user_custom_notification($user, $template, $keyboard);
                    }
                }

                $this->delete_message_by_types([16, 17, 18, 19, 20]);
            }
        }

//        будет харниться массив со значениями:
//        $notification = [
//            'text_ru' => 'text',
//            'text_en' => 'text',
//            'buttons' => ['start' => 1, 'recommend' => 1]
//        ];
        if(!isset($flag)) {
            $this->delete_message_by_types([16, 17, 18, 19, 20]);
            $reset = $this->data->get('reset');

            $notification = ['buttons' => []];
            if(isset($reset)) $this->chat->storage()->set('notification', $notification);
            else $notification = $this->chat->storage()->get('notification');

            $template = $this->template_prefix.'create_notification';
            $keyboard = Keyboard::make()->buttons([
                Button::make(isset($notification['text_ru'])? "✅".$buttons_texts['text_ru']:$buttons_texts['text_ru'])
                    ->action('notification')
                    ->param('notification', 1)
                    ->param('text', 'ru'),

                Button::make(isset($notification['text_en'])? "✅".$buttons_texts['text_en']:$buttons_texts['text_en'])
                    ->action('notification')
                    ->param('notification', 1)
                    ->param('text', 'en'),

                Button::make(isset($notification['buttons']['start'])? "✅".$buttons_texts['start']:$buttons_texts['start'])
                    ->action('notification')
                    ->param('notification', 1)
                    ->param('button', 'start'),

                Button::make(isset($notification['buttons']['recommend'])? "✅".$buttons_texts['recommend']:$buttons_texts['recommend'])
                    ->action('notification')
                    ->param('notification', 1)
                    ->param('button', 'recommend'),
            ]);

            if(
                (isset($notification['text_ru']) AND isset($notification['text_en'])) AND
                (isset($notification['buttons']['start']) OR isset($notification['buttons']['recommend'])))
            {
                $keyboard->row([
                    Button::make($buttons_texts['preview'])
                        ->action('notification')
                        ->param('notification', 1)
                        ->param('preview', 1)
                        ->param('lang', 'en')
                ]);
            }

            $keyboard->row([
                Button::make($buttons_texts['cancel'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', '17,18')
            ]);

            $response = $this->chat
                ->message(view($template, ['notification' => $notification]))
                ->keyboard($keyboard)
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 17
            ]);
        }
    }

    public function commands(): void
    {
        $flag = $this->data->get('commands');

        if(isset($flag)) {

        }

        if(!isset($flag)) {
            $this->delete_message_by_types([3, 12, 16, 17, 18, 19, 20, 21, 22]);
            if(isset($this->message)) {
                $this->chat->deleteMessage($this->messageId)->send();
            }

            $template = 'Admin chat commands';
            $buttons_texts = $this->buttons['commands'];

            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['notification'])
                    ->action('notification')
                    ->param('reset', 1),

                Button::make($buttons_texts['bonuses'])
                    ->action('bonuses')
                    ->param('bonuses', 1)
                    ->param('user', 1)
            ]);

            $response = $this->chat
                ->message($template)
                ->keyboard($keyboard)
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 16
            ]);
        }
    }

    public function send_card(): void
    {
        $flag = $this->data->get('send');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        if (isset($flag)) {
            $confirm = $this->data->get('confirm');

            if (isset($confirm)) {
                $choice = $this->data->get('choice');

                if ($choice === 'yes') {
                    $tempt_file = File::where('order_id', $order->id)
                        ->where('file_type_id', 1)
                        ->where('order_status_id', null)
                        ->first();
                    $photo_path = $tempt_file->path;
                    $tempt_file->delete();

                    File::create([
                        'order_id' => $order->id,
                        'ticket_item_id' => null,
                        'file_type_id' => 1,
                        'order_status_id' => 14,
                        'path' => $photo_path
                    ]);

                    $order->payment->update(['status_id' => 3]);
                    $order->update(['status_id' => 14]);

                    $fake_dataset = [
                        'action' => 'send_card',
                        'params' => [
                            'order_id' => $order->id,
                            'edit' => 1
                        ]
                    ];

                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                    (new Admin())->handle($fake_request, $this->bot);
                }

                if ($choice === 'no') {
                    $this->delete_order_card_messages($order, true);
                    $order->payment->update([
                        'method_id' => null,
                        'status_id' => 1
                    ]);
                }

            }
        }

        if (!isset($flag)) {
            $edit = $this->data->get('edit');

            $template = $this->template_prefix . 'order_info';
            $buttons_texts = $this->buttons['send_card'];
            $buttons = [];
            $photo_path = null;
            /* Если статус_ид = 13 => подтверждение оплаты 2 и 3 метода */
            if ($order->status_id === 13) {
                $file = File::where('order_id', $order->id)
                    ->where('file_type_id', 1)
                    ->where('order_status_id', null)
                    ->first();
                $photo_path = $file->path;

                $buttons[] = Button::make($buttons_texts['confirm'])
                    ->action('send_card')
                    ->param('send', 1)
                    ->param('confirm', 1)
                    ->param('choice', 'yes')
                    ->param('order_id', $order->id);

                $buttons[] = Button::make($buttons_texts['decline'])
                    ->action('send_card')
                    ->param('send', 1)
                    ->param('confirm', 1)
                    ->param('choice', 'no')
                    ->param('order_id', $order->id);
            } // end if status_id === 13

            /* если равен 14 => значит заказ уже подтвержден */
            if($order->status_id === 14) {
                $file = File::where('order_id', $order->id)
                    ->where('file_type_id', 1)
                    ->orderBy('order_status_id', 'desc')
                    ->first();
                $photo_path = $file->path;
                $buttons[] = Button::make($this->general_buttons['report'])
                    ->action('fake');
            }

            $keyboard = Keyboard::make()->buttons($buttons);

            if(isset($edit)) {
                $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                    ->where('order_id', $order->id)
                    ->where('message_type_id', 1)
                    ->first();

                $this->chat
                    ->editMedia($chat_order->message_id)
                    ->photo(Storage::path($photo_path))
                    ->html(view($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();
            } {
                $response = $this->chat
                    ->photo(Storage::path($photo_path))
                    ->html(view($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();

                ChatOrderPivot::create([
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => $order->id,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 1
                ]);
            }
        }
    }

    public function handleChatMessage(Stringable $text): void
    {
        $photos = $this->message->photos();
        $text = $this->message->text();

        if($photos->isEmpty() AND isset($text)) {
            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $this->messageId,
                'message_type_id' => 12
            ]);

            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', null)
                ->whereIn('message_type_id', [18, 19, 21])
                ->first();

            if(in_array($chat_order->message_type_id, [18, 19])) {
                $notification = $this->chat->storage()->get('notification');
                if($chat_order->message_type_id === 18) $notification['text_ru'] = $text;
                else if($chat_order->message_type_id === 19) $notification['text_en'] = $text;
                $this->chat->storage()->set('notification', $notification);
                $fake_dataset = [
                    'action' => 'notification',
                    'params' => []
                ];
                $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                (new self())->handle($fake_request, $this->bot);

                $this->chat->deleteMessage($this->messageId)->send();
            } else if($chat_order->message_type_id === 21) { // запрос ид пользователя
                if(preg_match('#^[0-9]+$#', $text)) {
                    $user = UserModel::where('id', $text)->first();
                    if(isset($user)) {
                        $fake_dataset = [
                            'action' => 'bonuses',
                            'params' => [
                                'user_id' => $user->id
                            ]
                        ];

                        $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                        (new self())->handle($fake_request, $this->bot);
                    } else {
                        $response = $this->chat
                            ->message('There is no user with the specified ID, please try again:')
                            ->send();

                        ChatOrderPivot::create([
                            'telegraph_chat_id' => $this->chat->id,
                            'order_id' => null,
                            'message_id' => $response->telegraphMessageId(),
                            'message_type_id' => 3
                        ]);
                    }
                } else {
                    $response = $this->chat
                        ->message('Invalid user ID entered, please try again:')
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => null,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 3
                    ]);
                }
            }
        }
    }
}
