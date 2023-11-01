<?php

namespace App\Http\Webhooks\Handlers;
use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\ChatOrderPivot;
use App\Models\Order;
use App\Models\File;
use App\Models\OrderMessage;
use App\Models\OrderServicePivot;
use App\Models\Service;
use App\Services\FakeRequest;
use App\Services\Helper;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Storage;

class Courier extends WebhookHandler
{
    use ChatsHelperTrait;
    public function __construct()
    {
        $this->buttons = config('buttons.courier');
        $this->general_buttons = config('buttons.chats');
        $this->template_prefix = 'bot.chats.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void
    {
        if(in_array($order->status_id, [3, 5, 9, 10, 11, 12])) {
            $keyboard = $this->get_keyboard_order_card($order);
            $this->show_card($order, $keyboard);
        } else if($order->status_id === 13 AND $order->payment->method_id === 1) {
            $keyboard = $this->get_keyboard_order_card($order);
            $this->show_card($order, $keyboard);
        }
    }

    public function get_keyboard_order_card(Order $order = null): Keyboard
    {
        $buttons = [];
        if($order->status_id === 9) { // взвешивание
            $buttons[] = Button::make($this->buttons[$order->status_id])
                ->action('weigh')
                ->param('order_id', $order->id);
        } else {
            $buttons[] = Button::make($this->buttons[$order->status_id])
                ->action('show_card')
                ->param('show_card', 1)
                ->param('order_id', $order->id);

            if($order->status_id === 12) {
                $buttons[] = Button::make('Dialogue')
                    ->action('order_dialogue')
                    ->param('order_id', $order->id);
            } else if($order->status_id === 13) {
                $buttons[] = Button::make("Didn't receive money")
                    ->action('decline_payment')
                    ->param('order_id', $order->id);
            }
        }
        $buttons[] = Button::make($this->general_buttons['report'])
            ->action('order_report')
            ->param('order_id', $order->id);

        return Keyboard::make()->buttons($buttons);
    }

    public function decline_payment(): void
    {
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        $this->delete_message_by_types([1], $order);
        $order->payment->update([
            'method_id' => null,
            'status_id' => 1
        ]);

        /* происходит обновление страницы с оплатой у пользователя через наблюдатель */
    }

    public function order_dialogue(Order $order = null): void // обработка диалога с пользователем
    {
        $flag = $this->data->get('dialogue');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::where('id', $order_id)->first();
        $main_chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();
        $buttons_texts = $this->general_buttons['courier_dialogue'];

        if(isset($flag)) {
            $write = $this->data->get('write');
            $get_message = $this->data->get('get');

            if(isset($write)) { // запрос сообщения
                $template = $this->template_prefix.'request_dialogue_message';
                $keyboard = Keyboard::make()->buttons([
                    Button::make('Cancel')->action('delete_message_by_types')
                        ->param('delete', 1)
                        ->param('type_id', '14')
                ]);

                $response = $this->chat
                    ->message(view($template))
                    ->reply($main_chat_order->message_id)
                    ->keyboard($keyboard)
                    ->send();

                ChatOrderPivot::create([
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => $order->id,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 14
                ]);
            }

            if(isset($get_message)) { // получить сообщение клиента
                $new_order_message = OrderMessage::where('order_id', $order->id)
                    ->orderBy('created_at', 'desc')
                    ->first(); // получаем последнее сообщение
                $template = $this->template_prefix.'client_order_message';
                $template_dataset = [
                    'order' => $order,
                    'order_message' => $new_order_message
                ];

                $dialogue_chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                    ->where('order_id', $order->id)
                    ->where('message_type_id', 13)
                    ->first();

                /* Если диалог открыт, тогда у уведомления с новым сообщением от клиента не будет кнопки ответить */
                /* так же редактируем открытый диалог */
                $buttons = [];
                if(isset($dialogue_chat_order)) {
                    $fake_dataset = [
                        'action' => 'order_dialogue',
                        'params' => [
                            'order_id' => $order->id,
                            'edit' => 1
                        ]
                    ];

                    $fake_request = FakeRequest::callback_query($this->chat, $this->bot, $fake_dataset);
                    (new self())->handle($fake_request, $this->bot);
                } else {
                    $buttons[] = Button::make($buttons_texts['reply_to_message'])
                        ->action('order_dialogue')
                        ->param('dialogue', 1)
                        ->param('write', 1)
                        ->param('order_id', $order->id);
                }

                $buttons[] = Button::make($buttons_texts['close_message'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', '15')
                    ->param('order_id', $order->id);

                $keyboard = Keyboard::make()->buttons($buttons);

                $response = $this
                    ->chat
                    ->message(view($template, $template_dataset))
                    ->reply($main_chat_order->message_id)
                    ->keyboard($keyboard)
                    ->send();

                ChatOrderPivot::create([
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => $order->id,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 15
                ]);
            }
        }

        if(!isset($flag)) {
            $edit = $this->data->get('edit'); // прилетело с кнопки,но с параметром редактировать
            $order_messages = OrderMessage::where('order_id', $order->id)->get();
            $template_dataset = [
                'order_messages' => $order_messages,
                'current_chat_id' => $this->chat->chat_id,
                'order' => $order
            ];
            $template = $this->template_prefix.'courier_dialogue';
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['write'])
                    ->action('order_dialogue')
                    ->param('dialogue', 1)
                    ->param('write', 1)
                    ->param('order_id', $order->id),

                Button::make($buttons_texts['close'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', '12,13,14')
            ]);
            if(isset($this->message) OR isset($edit)) { // редактируем
                $dialogue_chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                    ->where('order_id', $order->id)
                    ->where('message_type_id', 13)
                    ->first();

                if(isset($dialogue_chat_order)) {
                    $response = $this->chat->message(view($template, $template_dataset))
                        ->edit($dialogue_chat_order->message_id)
                        ->keyboard($keyboard)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => $order->id,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 13
                    ]);
                }

            } else { // очищаем и отправляем заново
                $this->delete_order_card_messages($order);
                $response = $this->chat
                    ->message(view($template, $template_dataset))
                    ->reply($main_chat_order->message_id)
                    ->keyboard($keyboard)
                    ->send();

                ChatOrderPivot::create([
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => $order->id,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 13
                ]);
            }

        }
    }

    public function weigh(): void
    {
        $flag = $this->data->get('weigh');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();
        $buttons_texts = $this->general_buttons['weighing'];

        if(isset($flag)) { // обработка
            $this->delete_message_by_types([10]);
            $choice = $this->data->get('choice');
            $reset = $this->data->get('reset');
            $accept = $this->data->get('accept');

            if($choice) {
                $this->delete_message_by_types([3, 10]);
                $service = Service::where('id', $choice)->first(); // текущая выбранная услуга
                /* $order_services информация о выбранных услугах для заказа(выбранные, текущая) */
                /* в выбранные попадают только те, у которых указано количество! */
                /* так же по цифровым ключам располагается количество (ид услуги = количество) */
                $order_services = $this->chat->storage()->get('order_services');
                $request_text = null; // текст запроса количества
                if(!isset($order_services['selected'])) {
                    $order_services = ['current' => $service->id];
                    $request_text = $service->request_text;
                } else {
                    if(!in_array($service->id, $order_services['selected'])) {
                        $order_services['current'] = $service->id;
                        $request_text = $service->request_text;
                    } else {
                        $order_services['current'] = null;
                        foreach ($order_services['selected'] as $key => $selected_service_id) {
                            if($service->id === $selected_service_id) unset($order_services['selected'][$key]);
                        }
                    }
                }
                $this->chat->storage()->set('order_services', $order_services);

                if(isset($request_text)) { // значит выбраная услуга ранее не выбранная
                    $response = $this->chat
                        ->message($request_text)
                        ->reply($this->messageId)
                        ->keyboard(Keyboard::make()->buttons([
                            Button::make($buttons_texts['cancel'])
                                ->action('delete_message_by_types')
                                ->param('delete', 1)
                                ->param('type_id', '3,10,12')
                        ]))
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => $order->id,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 10
                    ]);
                } else {
                    $keyboard = $this->get_weighing_keyboard($order->id);
                    $this->chat->replaceKeyboard($this->messageId, $keyboard)->send();
                }
            }

            if(isset($reset)) {
                $this->delete_message_by_types([3, 10, 12]);
                $this->chat->storage()->set('order_services', null); // обнуляем выбранные услуги
                $keyboard = $this->get_weighing_keyboard($order->id);
                $template = $this->template_prefix.'weighing';
                $this->chat
                    ->edit($this->messageId)
                    ->message(view($template))
                    ->keyboard($keyboard)
                    ->send();
            }

            if(isset($accept)) {
                $order_services = $this->chat->storage()->get('order_services');

                if(isset($order_services['selected'])) {
                    $array_without_empty_values = array_diff($order_services['selected'], ['', null, false]);
                    if(!empty($array_without_empty_values)) {
                        $price = Helper::get_price($order_services);
                        foreach($price['services'] as $key => $service) {
                            OrderServicePivot::create([
                                'order_id' => $order->id,
                                'service_id' => $key,
                                'amount' => $service['amount']
                            ]);
                        }
                        $order->update([
                            'price' => $price['sum'],
                            'status_id' => 10
                        ]);
                    }
                } else {
                    $template = 'bot.notifications.selected_services_is_null';
                    $response = $this->chat
                        ->message(view($template))->reply($this->messageId)
                        ->send();

                    ChatOrderPivot::create([
                        'telegraph_chat_id' => $this->chat->id,
                        'order_id' => $order->id,
                        'message_id' => $response->telegraphMessageId(),
                        'message_type_id' => 3
                    ]);
                }

                // далее отправка будет в наблюдателе, тк создастся запись новая => запись в стэк-трейсе заказа
            }
        }

        if(!isset($flag)) { // просьба взвешать вещи (тип сообщения=9)
            $this->delete_message_by_types([3, 5, 6, 7, 9, 10, 11, 12]);
            $this->chat->storage()->set('order_services', null); // обнуляем выбранные услуги

            $main_chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();
            $template = $this->template_prefix.'weighing';

            $keyboard = $this->get_weighing_keyboard($order->id);

            $response = $this->chat->message(view($template))
                ->reply($main_chat_order->message_id)
                ->keyboard($keyboard)
                ->send();

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 9
            ]);
        }
    }

    public function show_card(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('show_card');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) {
            if($order->status_id === 11) {
                $order->update([
                    'status_id' => ++$order->status_id
                ]);
                /* В этот момент в наблюателе происходит отправка уведомление клиенту */
                /* так же через наблюдатель происходит отправка новой карточки заказа */
            } else {
                $this->delete_message_by_types([5, 6, 7]);
                $this->request_photo($order);
            }
        }

        if(!isset($flag)) {
            $template = $this->template_prefix.'order_info';
            $response = null;

            $photo = File::where('order_id', $order->id)
                ->where('file_type_id', 1)
                ->orderBy('order_status_id', 'desc')
                ->first();

            if(!isset($photo)) {
                $response = $this->chat
                    ->message(view($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();
            } else {
                $response = $this->chat->photo(Storage::path($photo->path))
                    ->message(view($template, ['order' => $order]))
                    ->keyboard($keyboard)
                    ->send();
            }

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 1
            ]);
        }
    }

    public function refresh(string $order_id): void
    {
        ChatOrderPivot::create([
            'telegraph_chat_id' => $this->chat->id,
            'order_id' => null,
            'message_id' => $this->messageId,
            'message_type_id' => 4
        ]);

        if($order_id == '/refresh') {
            $this->refresh_chat();
        } else {
            $order = $this->check_order_message_existence_in_chat($order_id);
            if(isset($order)) {
                $this->delete_message_by_types([3, 4]);
                $this->update_order_card($order);
            }
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $photos = $this->message->photos();
        $text = $this->message->text(); // обычный текст

        if(isset($text) AND $photos->isEmpty()) { // просто текст
            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $this->messageId,
                'message_type_id' => 12
            ]);

            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->whereIn('message_type_id', [10, 14]) // запрос кг или сообщения в диалог
                ->first();

            if(isset($chat_order)) {
                if($chat_order->message_type_id === 10) { // обработка запроса кг
                    if(preg_match('#^[0-9]+$#', $text)) {
                        $order_services = $this->chat->storage()->get('order_services');
                        if(isset($order_services['selected'])) $order_services['selected'][] = $order_services['current'];
                        else $order_services['selected'] = [$order_services['current']];
                        $order_services[$order_services['current']] = $text;
                        $this->chat->storage()->set('order_services', $order_services);

                        $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                            ->where('message_type_id', 9)
                            ->first();

                        $keyboard = $this->get_weighing_keyboard($chat_order->order->id);
                        $price = Helper::get_price($order_services);
                        $template = $this->template_prefix.'weighing';
                        $this->chat
                            ->edit($chat_order->message_id)
                            ->message(view($template, ['price' => $price]))
                            ->keyboard($keyboard)
                            ->send();
                        $this->delete_message_by_types([3, 10, 12]);
                    } else {
                        $response = $this->chat
                            ->message('Invalid value entered! Try again!')
                            ->send();

                        ChatOrderPivot::create([
                            'telegraph_chat_id' => $this->chat->id,
                            'order_id' => $chat_order->order->id,
                            'message_id' => $response->telegraphMessageId(),
                            'message_type_id' => 3
                        ]);
                    }
                } else if($chat_order->message_type_id === 14) { // обработка сообщения клиенту
                    $message_from_client_chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                        ->where('order_id', $chat_order->order->id)
                        ->where('message_type_id', 15)
                        ->first();

                    if(isset($message_from_client_chat_order)) {
                        $this->delete_message_by_types([15], $chat_order->order);
                    }

                    $this->delete_message_by_types([12, 14]);
                    OrderMessage::create([
                        'order_id' => $chat_order->order->id,
                        'sender_chat_id' => $this->chat->chat_id,
                        'text' => $text
                    ]);
                    $this->order_dialogue($chat_order->order);
                }
            }
        }

        if(isset($photos) AND $photos->isNotEmpty()) { // обработка прилетевших фотографий
            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $this->messageId,
                'message_type_id' => 8
            ]);

            $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 5)
                ->first();

            $order = isset($chat_order)? $chat_order->order: null;

            $message_timestamp = $this->message->date()->timestamp; // время отправки прилетевшего фото
            $last_message_timestamp = $this->chat->storage()->get('photo_message_timestamp'); // timestamp предыдущего прилетевшего фото

            if($message_timestamp !== $last_message_timestamp) {
                $photo = $this->save_photo($photos, $order);
                $this->chat->storage()->set('photo_id', $photo->id());

                if(isset($chat_order)) { // если есть запрос на фото
                    $this->delete_message_by_types([5, 8]);
                    $this->confirm_photo($photo, $order);
                }

                if(!isset($chat_order)) { // если фото было просто закинуто
                    $this->delete_message_by_types([2, 3, 4, 5, 6, 7, 8]);
                    $this->select_order($photo);
                }
            } else {
                $this->delete_message_by_types([8]);
            }

            $this->chat->storage()->set('photo_message_timestamp', $message_timestamp);

        }
    }

    private function get_weighing_keyboard(int $order_id): Keyboard
    {
        $services = Service::all();
        $order_services = $this->chat->storage()->get('order_services');
        $buttons_texts = $this->general_buttons['weighing'];

        $accept_button = Button::make($buttons_texts['accept'])
            ->action('weigh')
            ->param('weigh', 1)
            ->param('accept', 1)
            ->param('order_id', $order_id);

        $reset_button = Button::make($buttons_texts['reset'])
            ->action('weigh')
            ->param('weigh', 1)
            ->param('reset', 1)
            ->param('order_id', $order_id);

        $cancel_buttons = Button::make($buttons_texts['cancel'])
            ->action('delete_message_by_types')
            ->param('delete', 1)
            ->param('type_id', '3,9,10,12');

        $service_buttons = [];
        foreach ($services as $service) {
            $button_text = null;
            if(!isset($order_services['selected'])) $button_text = $service->title;
            else {
                if(in_array($service->id, $order_services['selected'])) $button_text = "✅{$service->title}";
                else $button_text = $service->title;
            }

            $service_buttons[] = Button::make($button_text)
                ->action('weigh')
                ->param('weigh', 1)
                ->param('order_id', $order_id)
                ->param('choice', $service->id)
                ->width(0.5);
        }

        $keyboard_buttons = $service_buttons;
        $keyboard_buttons[] = $accept_button;
        $keyboard_buttons[] = $reset_button;
        $keyboard_buttons[] = $cancel_buttons;

        return Keyboard::make()->buttons($keyboard_buttons);
    }

}
