<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\OrderMessage;
use App\Models\OrderServicePivot;
use App\Models\OrderStatusPivot;
use App\Models\Payment;
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

    public function delete_order(): void
    {
        $flag = $this->data->get('delete');

        if(isset($flag)) {
            $order_id = $this->data->get('order_id');
            $files = File::where('order_id', $order_id)->get();
            if($files->isNotEmpty()) {
                foreach ($files as $file) {
                    Storage::delete($file->path);
                    $file->delete();
                }
            }

            $order_messages = OrderMessage::where('order_id', $order_id)->get();
            if($order_messages->isNotEmpty()) {
                foreach ($order_messages as $order_message) {
                    $order_message->delete();
                }
            }

            $order_services = OrderServicePivot::where('order_id', $order_id)->get();
            if($order_services->isNotEmpty()) {
                foreach ($order_services as $order_service) {
                    $order_service->delete();
                }
            }

            $order_statuses = OrderStatusPivot::where('order_id', $order_id)->get();
            if($order_statuses->isNotEmpty()) {
                foreach($order_statuses as $order_status) {
                    $order_status->delete();
                }
            }

            $payments = Payment::where('order_id', $order_id)->get();
            if($payments->isNotEmpty()) {
                foreach ($payments as $payment) {
                    $payment->delete();
                }
            }

            $chat_orders = ChatOrderPivot::where('order_id', $order_id)
                ->where('message_type_id', 1)
                ->get();
            if($chat_orders->isNotEmpty()) {
                $fake_dataset = [
                    'action' => 'delete_order',
                    'params' => [
                        'order_id' => $order_id
                    ]
                ];
                $chat_handler_class_prefix = '\\App\\Http\\Webhooks\\Handlers\\';
                foreach ($chat_orders as $chat_order) {
                    $fake_request = FakeRequest::callback_query($chat_order->chat, $this->bot, $fake_dataset);
                    $handler_class = $chat_handler_class_prefix.$chat_order->chat->name;
                    (new $handler_class())->handle($fake_request, $this->bot);
                }
            }

            $order = Order::where('id', $order_id)->first();
            $user = $order->user;
            $order->delete();
            $this->delete_message_by_types([25]);
            $response = $this->chat
                ->message("Order #{$order_id} has been deleted")
                ->keyboard(Keyboard::make()
                    ->buttons([
                        Button::make('OK')
                            ->action('delete_message_by_types')
                            ->param('delete', 1)
                            ->param('type_id', '3')])
                )->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'user_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 3
            ]);

            // отправка уведомления пользователю чей был заказ
            $template = "bot.user.".$user->language_code.'.notifications.delete_order';
            $start_button_texts = ['ru' => 'Заказать стирку', 'en' => 'Order Laundry'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($start_button_texts[$user->language_code])
                    ->action('start')
            ]);
            $template_text = view($template, ['order_id' => $order_id]);
            Helper::send_user_custom_notification($user, $template_text, $keyboard);
        }

        if(!isset($flag)) {
            $this->delete_message_by_types([16]);
            $orders = Order::whereIn('status_id', [2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13])->get();
            $template = null;
            $buttons = [];
            if($orders->isNotEmpty()) {
                $template = 'Select the order you want to delete:';
                foreach ($orders as $order) {
                    $buttons[] = Button::make("#{$order->id} ({$order->status->en_desc})")
                        ->action('delete_order')
                        ->param('delete', 1)
                        ->param('order_id', $order->id);
                }
            } else $template = 'There are no active orders that can be deleted.';

            $buttons[] = Button::make('Cancel')
                ->action('delete_message_by_types')
                ->param('delete', 1)
                ->param('type_id', '25');

            $keyboard = Keyboard::make()->buttons($buttons);
            $response = $this->chat
                ->message($template)
                ->keyboard($keyboard)
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'user_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 25
            ]);
        }
    }

    public function bonuses(): void
    {
        $flag = $this->data->get('bonuses');

        if(isset($flag)) {
            $user = $this->data->get('user'); // запрос юзер_ид
            $plus = $this->data->get('plus');
            $minus = $this->data->get('minus');

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
            } else if(isset($plus) OR isset($minus)) {
                $user_id = $this->data->get('user_id');
                $user = UserModel::where('id', $user_id)->first();

                if(isset($plus)) { // запрос на количество бонусов
                    $template = 'Enter the number of bonuses you want to award to the user';
                    $keyboard = Keyboard::make()->buttons([
                        Button::make('Cancel')
                            ->action('delete_message_by_types')
                            ->param('delete', 1)
                            ->param('type_id', '23')
                    ]);

                    $response = $this->chat
                        ->message($template)
                        ->keyboard($keyboard)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => null,
                        'user_id' => $user->id,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 23
                    ]);
                }

                if(isset($minus)) {
                    $template = 'Enter the number of bonuses you want to deduct from the user:';
                    $keyboard = Keyboard::make()->buttons([
                        Button::make('Cancel')
                            ->action('delete_message_by_types')
                            ->param('delete', 1)
                            ->param('type_id', '24')
                    ]);

                    $response = $this->chat
                        ->message($template)
                        ->keyboard($keyboard)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => null,
                        'user_id' => $user->id,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 24
                    ]);
                }
            }
        }

        if(!isset($flag)) {
            $this->delete_message_by_types([3, 12, 21, 22, 23, 24]);
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
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', '22')
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
            $photo = $this->data->get('photo');

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
                    $response = null;
                    if(isset($notification['photo']) AND isset($notification['photo']['id'])) {
                        $photo_path = Storage::path("{$this->chat->name}/{$notification['photo']['id']}.jpg");
                        $response = $this->chat
                            ->photo($photo_path)
                            ->html($template)
                            ->keyboard($keyboard)
                            ->send();
                    } else {
                        $response = $this->chat
                            ->html($template)
                            ->keyboard($keyboard)
                            ->send();
                    }

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

            if(isset($photo)) {
                if(isset($notification['photo'])) {
                    /* Если инфа о фото уже есть, тогда убираем её */
                    /* после отправляем на рен деринг заново */
                    Storage::delete($this->chat->name.'/'.$notification['photo']['id'].'.jpg');
                    unset($notification['photo']);
                    $this->chat->storage()->set('notification', $notification);

                    $fake_dataset = [
                        'action' => 'notification',
                        'params' => []
                    ];
                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                    (new self())->handle($fake_request, $this->bot);
                } else {
                    $this->request_photo();
                }
            }
        }

//        будет харниться массив со значениями:
//        $notification = [
//            'text_ru' => 'text',
//            'text_en' => 'text',
//            'buttons' => ['start' => 1, 'recommend' => 1],
//            'photo' => ['id' => photo_id, 'timestamp' => last_message_timestamp]
//        ];
        if(!isset($flag)) {
            $this->delete_message_by_types([5, 16, 17, 18, 19, 20]);
            $reset = $this->data->get('reset');

            $notification = $this->chat->storage()->get('notification');
            if(isset($reset)) {
                if(isset($notification['photo']) AND isset($notification['photo']['id'])) {
                    Storage::delete($this->chat->name.'/'.$notification['photo']['id'].'.jpg');
                }
                $notification = ['buttons' => []];
                $this->chat->storage()->set('notification', $notification);
            }

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

                Button::make(isset($notification['photo'])? "✅".$buttons_texts['photo']: $buttons_texts['photo'])
                    ->action('notification')
                    ->param('notification', 1)
                    ->param('photo', 1),

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
            $this->delete_message_by_types([3, 12, 16, 17, 18, 19, 20, 21, 22, 23, 24]);
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
                    ->param('user', 1),

                Button::make($buttons_texts['delete'])
                    ->action('delete_order')
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
                    /* Удаляем текущую карточку */
                    $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                        ->where('order_id', $order->id)
                        ->where('message_type_id', 1)
                        ->first();
                    $this->chat->deleteMessage($chat_order->message_id)->send();
                    $chat_order->delete();
                    $order->update(['status_id' => 14]);

//                    $fake_dataset = [
//                        'action' => 'send_card',
//                        'params' => [
//                            'order_id' => $order->id,
//                            'edit' => 1
//                        ]
//                    ];
//
//                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
//                    (new Admin())->handle($fake_request, $this->bot);
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
            $photo_path = null;
            /* Если статус_ид = 13 => подтверждение оплаты 2 и 3 метода */
            if ($order->status_id === 13) {
                $file = File::where('order_id', $order->id)
                    ->where('file_type_id', 1)
                    ->where('order_status_id', null)
                    ->first();
                $photo_path = $file->path;
            } // end if status_id === 13

            /* если равен 14 => значит заказ уже подтвержден */
            if($order->status_id === 14) {
                $file = File::where('order_id', $order->id)
                    ->where('file_type_id', 1)
                    ->orderBy('order_status_id', 'desc')
                    ->first();
                $photo_path = $file->path;
            }

            $keyboard = $this->get_keyboard_order_card($order);

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

    public function get_keyboard_order_card(Order $order = null): Keyboard
    {
        $buttons_texts = $this->buttons['send_card'];
        $keyboard = Keyboard::make();
        $buttons = [];
        if ($order->status_id === 13) {
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

        if($order->status_id === 14) {
            $buttons[] = Button::make($this->general_buttons['report'])
                ->action('order_report')
                ->param('order_id', $order->id);
        }

        $keyboard->buttons($buttons);
        return $keyboard;
    }

    public function handleChatMessage(Stringable $text): void
    {
        $photos = $this->message->photos();
        $text = $this->message->text();

        if(isset($photos) AND $photos->isNotEmpty()) { // прикрепление фото
            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', null)
                ->whereIn('message_type_id', [5])
                ->first();

            if(isset($chat_order)) { // если был запрос на фото
                $message_timestamp = $this->message->date()->timestamp; // время отправки прилетевшего фото
                $notification = $this->chat->storage()->get('notification');
                $updated_notification = $notification;
                $save_photo_flag = false;
                if(isset($notification['photo'])) { // если инфа о фото была установлена
                    $last_photo_timestamp = $notification['photo']['timestamp'];
                    if(isset($last_photo_timestamp) AND $message_timestamp !== $last_photo_timestamp) {
                        /* установлена и не равна текущему => сохраняем фото */
                        $save_photo_flag = true;
                    }
                } else {
                    $save_photo_flag = true;
                }

                if($save_photo_flag) {
                    $photo = $this->save_photo($photos, false);
                    $updated_notification['photo']['id'] = $photo->id();
                    $updated_notification['photo']['timestamp'] = $message_timestamp;
                    $this->chat->storage()->set('notification', $updated_notification);

                    $fake_dataset = [
                        'action' => 'notification',
                        'params' => []
                    ];
                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                    (new self())->handle($fake_request, $this->bot);
                }
            }

            $this->chat->deleteMessage($this->messageId)->send();
        }

        if($photos->isEmpty() AND isset($text)) {
            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $this->messageId,
                'message_type_id' => 12
            ]);

            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', null)
                ->whereIn('message_type_id', [18, 19, 21, 23, 24])
                ->first();

            if(isset($chat_order)) {
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
                } else if($chat_order->message_type_id === 23 OR $chat_order->message_type_id === 24) { // добавление бонусов
                    if(preg_match('#^[0-9]+$#', $text)) {
                        $user = $chat_order->user;
                        $template_prefix = 'bot.user.'.$user->language_code.".notifications.";
                        $start_text = ['ru' => 'Заказать стирку', 'en' => 'Order Laundry'];
                        $template_text = null;
                        $keyboard = Keyboard::make()->buttons([
                            Button::make($start_text[$user->language_code])->action('start')
                        ]);
                        if($chat_order->message_type_id === 23) {
                            $new_balance = (int)$user->balance + (int)$text;
                            $user->update(['balance' => $new_balance]);
                            // отправка уведомления клиенту
                            $template = $template_prefix."balance_replenished";
                            $template_text = view($template, ['plus' => $text, 'user' => $user]);
                        } else if($chat_order->message_type_id === 24) {
                            $new_balance = (int)$user->balance - (int) $text;
                            $user->update(['balance' => $new_balance]);

                            $template = $template_prefix."balance_updated";
                            $template_text = view($template, ['minus' => $text, 'user' => $user]);
                        }
                        // обновление у админа
                        $fake_dataset = [
                            'action' => 'bonuses',
                            'params' =>  [
                                'user_id' => $user->id
                            ]
                        ];
                        $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                        (new self())->handle($fake_request, $this->bot);

                        Helper::send_user_custom_notification($user, $template_text, $keyboard);
                    } else {
                        $response = $this->chat
                            ->message('Invalid value, please try again:')
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
}
