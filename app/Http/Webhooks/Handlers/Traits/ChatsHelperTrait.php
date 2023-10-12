<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use App\Models\ChatOrder;
use App\Models\Order;
use App\Models\OrderStatus;
use DefStudio\Telegraph\DTO\Photo;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait ChatsHelperTrait
{
    public function update_order_card_through_command(Order $order)
    {
        $chat_orders = ChatOrder::where('order_id', $order->id)
            ->where('telegraph_chat_id', $this->chat->id)
            ->get();

        foreach ($chat_orders as $chat_order) {
            if($chat_order->message_type_id === 1) {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
                $this->send_order_card($order);
            }
            else {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
            }
        }
    }
    public function update_order_card(Order $order, Keyboard $keyboard = null)
    {
        $chat_orders = ChatOrder::where('order_id', $order->id)
            ->where('telegraph_chat_id', $this->chat->id)
            ->get();

        $chat_order_main = $chat_orders
            ->where('message_type_id', 1)
            ->first();

        $template = $this->template_prefix.'order_info';

        foreach ($chat_orders as $chat_order) {
            if($chat_order->message_type_id != 1) {
                $this->chat
                    ->deleteMessage($chat_order->message_id)
                    ->send();
                $chat_order->delete();
            }
        }

        if(isset($keyboard)) {
            $this->chat
                ->edit($chat_order_main->message_id)
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
        } else {
            $this->chat
                ->edit($chat_order_main->message_id)
                ->message(view($template, ['order' => $order]))
                ->send();
        }
    }

    public function update_all_orders_cards_command():void
    {
        $orders = Order::whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('chat_order')
                ->whereColumn('chat_order.order_id', 'orders.id')
                ->where('telegraph_chat_id', $this->chat->id);
        })
            ->get();

        if($orders->count() > 0) {
            foreach ($orders as $order) {
                $this->update_order_card_through_command($order);
            }
        }
    }

    public function check_order_existence_in_chat_message(string|int $order_id): Order|null
    {
        $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
            ->where('order_id', $order_id)
            ->first();

        if(isset($chat_order)) {
            return $chat_order->order;
        } else {
            $template = 'bot.notifications.order_is_null';
            $response = $this->chat
                ->message(view($template, ['order_id' => $order_id]))
                ->send();

            DB::table('chat_order')->insert([
                [
                    'telegraph_chat_id' => $this->chat->id,
                    'order_id' => null,
                    'message_id' => $response->telegraphMessageId(),
                    'message_type_id' => 3
                ]
            ]);
            return null;
        }
    }

    public function remove_other_messages(): void
    {
        $other_messages = ChatOrder::where('telegraph_chat_id', $this->chat->id)
            ->where('order_id', null)
            ->get();
        if($other_messages->count() > 0) {
            foreach ($other_messages as $other_message) {
                $this->chat->deleteMessage($other_message->message_id)->send();
                $other_message->delete();
            }
        }
    }

    /* МЕТОДЫ ОБРАБОТКИ ДОБАВЛЕНИЯ ФОТО */

    /* request_photo отвечает только за отправку сообщения, своих кнопок у него нет (которые обрабатываются им же)
       есть одна кнопка "cancel", она обрабатывается delete_message_by_types */
    public function request_photo(Order $order):void
    {
        $template = $this->template_prefix.'photo_request';
        $buttons_texts = $this->config['request_photo'];

        $response = $this->chat
            ->message(view($template, ['order' => $order]))
            ->keyboard(Keyboard::make()->buttons([
                Button::make($buttons_texts['cancel'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', 5)
            ]))->send();

        ChatOrder::create([
            'telegraph_chat_id' => $this->chat->id,
            'order_id' => $order->id,
            'message_id' => $response->telegraphMessageId(),
            'message_type_id' => 5
        ]);
    }

    public function confirm_photo(Photo $photo = null, Order $order = null): void
    {
        $flag = $this->data->get('confirm_photo');

        if(isset($flag)) {
            $choice = $this->data->get('choice');
            if(isset($choice)) {
                $order_id = $this->data->get('order_id');
                $order = Order::where('id', $order_id)->first();

                if($choice == 1) { // YES
                    // удаляем текущее сообщение
                    // добавляем путь до фото в БД
                    // обновляем карточку заказа
                } else if($choice == 2) { // NO
                    $this->delete_message_by_types([6]);
                    $this->request_photo($order);
                }
            }
        }

        if(!isset($flag)) {
            $order = isset($chat_order)? $chat_order->order: $order;
            $dir = "{$this->chat->name}/order_{$order->id}";
            $file_name = $photo->id().".jpg";
            $buttons_texts = $this->config['confirm_photo'];
            $template = $this->template_prefix.'confirm_photo';

            $response = $this->chat->photo(Storage::path("{$dir}/{$file_name}"))
                ->html(view($template, ['order' => $order]))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make($buttons_texts['yes'])
                        ->action('confirm_photo')
                        ->param('confirm_photo', 1)
                        ->param('choice', 1)
                        ->param('order_id', $order->id),
                    Button::make($buttons_texts['no'])
                        ->action('confirm_photo')
                        ->param('confirm_photo', 1)
                        ->param('choice', 2)
                        ->param('order_id', $order->id),
                    Button::make($buttons_texts['cancel'])
                        ->action('delete_message_by_types')
                        ->param('delete', 1)
                        ->param('type_id', 6)
                ]))->send();

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 6
            ]);
        }
    }

    public function select_order(Photo $photo): void
    {
        $flag = $this->data->get('select_order');

        if(isset($flag)) {

        }

        if(!isset($flag)) {
            $chat_orders = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 1)
                ->get();
            $message_type_id = null;
            $response = null;
            if($chat_orders->isNotEmpty()) { // если в чате есть карточки заказа
                $buttons = [];
                foreach ($chat_orders as $chat_order) {
                    $buttons[] = Button::make("#{$chat_order->order->id}")
                        ->action('select_order')
                        ->param('select_order', 1)
                        ->param('order_id', $chat_order->order->id);
                }

                $buttons_texts = $this->config['select_order'];
                $buttons[] = Button::make($buttons_texts['cancel'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', 7);

                $template = $this->template_prefix."select_order";
                $path = "{$this->chat->name}/order_undefined/{$photo->id()}.jpg";
                $response = $this->chat->photo(Storage::path($path))
                    ->html(view($template))
                    ->keyboard(Keyboard::make()
                        ->buttons($buttons)
                    )->send();
                $message_type_id = 7;
            } else { // если в чате нет карточек заказа
                $response = $this->chat
                    ->message('в чате отсутствуют заказы к которым можно прикрепить это фото')
                    ->send();
                $message_type_id = 3;
            }

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => $message_type_id
            ]);
        }
    }

    public function delete_message_by_types(array $messages_types_id = null): void // массив типа [1,2,3], где значения - тайп_ид
    {
        $flag = $this->data->get('delete');

        if(isset($flag)) { // значит прилетело с кнопки
            $type_id = $this->data->get('type_id'); // тип сообщения
            $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', $type_id)
                ->first();

            $this->chat->deleteMessage($chat_order->message_id)->send();
            $chat_order->delete();
        }

        if(!isset($flag)) {
            if(isset($messages_types_id)) {
                $chat_orders = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                    ->whereIn('message_type_id', $messages_types_id)
                    ->get();

                if($chat_orders->isNotEmpty()) {
                    foreach ($chat_orders as $chat_order) {
                        $this->chat->deleteMessage($chat_order->message_id)->send();
                        $chat_order->delete();
                    }
                }
            }
        }
    }

    public function save_photo(Collection $photos, ChatOrder $chat_order = null): Photo
    {
        $photo = $photos->last(); // получение фото с лучшем качеством
        $dir = "{$this->chat->name}/";
        $file_name = $photo->id().".jpg";

        if(isset($chat_order)) { // если есть сообщение, которое просит отправить фото
            $dir = $dir."order_{$chat_order->order->id}";
        } else { // если фото просто так закинули в чат
            $dir = $dir."order_undefined";
        }

        Telegraph::store($photo, Storage::path($dir), $file_name); // сохранение фото

        return $photo;
    }
}
