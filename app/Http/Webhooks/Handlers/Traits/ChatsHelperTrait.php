<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\File;
use App\Models\Order;
use DefStudio\Telegraph\DTO\Photo;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait ChatsHelperTrait
{
    /* удаляет все сообщения связанные с карточкой заказа */
    /* отправляет на метод отправки новго сообщения */
    public function update_order_card(Order $order = null)
    {
        $flag = $this->data->get('update_order_card');

        if(isset($flag)) {
            $order_id = $this->data->get('order_id');
            $order = Order::where('id', $order_id)->first();
        }
        $this->delete_order_card_messages($order, true);
        $this->send_order_card($order);
    }

    public function refresh_chat()
    {
        $this->delete_message_by_types([3, 4, 7, 8]); // удаляем сообщения не относящиеся к карточкам заказов
        $main_chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id) // получаем все карточки заказа в чате
            ->where('message_type_id', 1)
            ->get();

        if($main_chat_orders->isNotEmpty()) {
            foreach($main_chat_orders as $chat_order) {
                $this->update_order_card($chat_order->order);
            }
        }
    }

    public function delete_order_card_messages(Order $order, bool $with_main = null): void
    {
        $chat_orders = null;

        if(!empty($with_main)) { // удаляет сообщения включая карточку заказа
            $chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->get();
        }

        if(empty($with_main)) { // удаляет все сообщения связанные с карточкой, но без главной карточки заказа

            $chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', '!=', 1)
                ->get();
        }

        foreach ($chat_orders as $chat_order) {
            $this->chat->deleteMessage($chat_order->message_id)->send();
            $chat_order->delete();
        }
    }

    public function delete_message_by_types(array $messages_types_ids = null, Order $order = null): void // массив типа [1,2,3], где значения - тайп_ид
    {
        $flag = $this->data->get('delete');

        if(isset($flag)) { // будет разбивать строку в массив с типами (если в params указано так '3,10,12'
            $type_id = $this->data->get('type_id');
            $messages_types_ids = explode(',', $type_id);
        }

        if (isset($messages_types_ids)) { // удаляет конкретные типы сообщения из чата
            $order_id = $this->data->get('order_id'); // если указан ордер_ид тогда удалятся конкретные типы сообщений, связанные с ним
            $chat_orders = collect();

            if((isset($order_id) AND isset($type_id)) OR isset($order)) {
                $order_id = isset($order_id)? $order_id: $order->id;
                $chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                    ->where('order_id', $order_id)
                    ->whereIn('message_type_id', $messages_types_ids)
                    ->get();
            } else {
                $chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                    ->whereIn('message_type_id', $messages_types_ids)
                    ->get();
            }

            if ($chat_orders->isNotEmpty()) {
                foreach ($chat_orders as $chat_order) {
                    $this->chat->deleteMessage($chat_order->message_id)->send();
                    $chat_order->delete();
                }
            }
        }
    }

    public function check_order_message_existence_in_chat(string|int $order_id): Order|null
    {
        $chat_order = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
            ->where('order_id', $order_id)
            ->first();

        if (isset($chat_order)) {
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

    /* МЕТОДЫ ОБРАБОТКИ ДОБАВЛЕНИЯ ФОТО */

    /* request_photo отвечает только за отправку сообщения, своих кнопок у него нет (которые обрабатываются им же)
       есть одна кнопка "cancel", она обрабатывается delete_message_by_types */
    public function request_photo(Order $order): void
    {
        $template = $this->template_prefix . 'photo_request';
        $buttons_texts = $this->general_buttons['request_photo'];

        $response = $this->chat
            ->message(view($template, ['order' => $order]))
            ->keyboard(Keyboard::make()->buttons([
                Button::make($buttons_texts['cancel'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', 5)
            ]))->send();

        ChatOrderPivot::create([
            'telegraph_chat_id' => $this->chat->id,
            'order_id' => $order->id,
            'message_id' => $response->telegraphMessageId(),
            'message_type_id' => 5
        ]);
    }

    public function confirm_photo(Photo $photo = null, Order $order = null): void
    {
        $flag = $this->data->get('confirm_photo');
        $order = isset($order) ? $order : Order::where('id', $this->data->get('order_id'))->first();

        $dir = "{$this->chat->name}/order_{$order->id}"; // путь к папке с фото текущей карточки заказа
        if (isset($flag)) {
            $choice = $this->data->get('choice');
            if (isset($choice)) {
                if ($choice == 1) { // YES
                    $this->push_photo_to_db_with_card($order);
                    $this->update_order_card($order);
                } else if ($choice == 2) { // NO
                    $this->delete_message_by_types([6]);
                    $this->request_photo($order);
                }
            }
        }

        if (!isset($flag)) {
            $file_name = $photo->id() . ".jpg";
            $buttons_texts = $this->general_buttons['confirm_photo'];
            $template = $this->template_prefix . 'confirm_photo';

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

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 6
            ]);
        }
    }

    public function select_order(Photo $photo = null): void
    {
        $flag = $this->data->get('select_order');

        if(isset($flag)) {
            $this->delete_message_by_types([7]);
            $order_id = $this->data->get('order_id');
            $order = Order::where('id', $order_id)->first();

            /* перенос фото из андефаинд в конкретную папку заказа */
            $photo_id = $this->chat->storage()->get('photo_id');
            $old_path = $this->chat->name . "/order_undefined" . "/{$photo_id}.jpg";
            $new_path = $this->chat->name . "/order_{$order->id}" . "/{$photo_id}.jpg";
            Storage::move($old_path, $new_path);

            $this->push_photo_to_db_with_card($order);
            $this->update_order_card($order);
        }

        if (!isset($flag)) {
            $chat_orders = ChatOrderPivot::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 1)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('orders')
                        ->whereColumn('orders.id', 'chat_order.order_id')
                        ->whereIn('orders.status_id', [3, 5, 6, 7, 10, 12, 13]);#
                })
                ->get();
            $message_type_id = null;
            $response = null;
            if ($chat_orders->isNotEmpty()) { // если в чате есть карточки заказа
                $buttons = [];
                foreach ($chat_orders as $chat_order) {
                    $buttons[] = Button::make("#{$chat_order->order->id}")
                        ->action('select_order')
                        ->param('select_order', 1)
                        ->param('order_id', $chat_order->order->id);
                }

                $buttons_texts = $this->general_buttons['select_order'];
                $buttons[] = Button::make($buttons_texts['cancel'])
                    ->action('delete_message_by_types')
                    ->param('delete', 1)
                    ->param('type_id', 7);

                $template = $this->template_prefix . "select_order";
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

            ChatOrderPivot::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => $message_type_id
            ]);
        }
    }

    public function save_photo(Collection $photos, Order $order = null): Photo
    {
        $photo = $photos->last(); // получение фото с лучшем качеством
        $dir = "{$this->chat->name}/";
        $file_name = $photo->id() . ".jpg";

        if (isset($order)) { // если известен заказ
            $dir = $dir . "order_{$order->id}";
        } else { // если заказ неизвестен
            $dir = $dir . "order_undefined";
        }

        Telegraph::store($photo, Storage::path($dir), $file_name); // сохранение фото

        return $photo;
    }

    /* сохранение фото с карточек заказа */
    public function push_photo_to_db_with_card(Order $order = null): void
    {
        $photo_id = $this->chat->storage()->get('photo_id');

        $status_id = $order->status_id;

        /* Здесь перед добавлением тк на статус ид 3 не должно быть фото */
        /* потому что этот статус обозначает отправлен курьеру, а след статус, когда пикап уже = 5 */
        if($this->chat->name === 'Courier' AND $order->status_id === 3) {
            $status_id = 5;
        } else  ++$status_id;

        if($status_id === 14) $order->payment->update(['status_id' => 3]); // ставим статус оплачено

        File::create([
            'order_id' => $order->id,
            'ticket_item_id' => null,
            'file_type_id' => 1,
            'path' => $this->chat->name . "/order_{$order->id}" . "/{$photo_id}.jpg",
            'order_status_id' => $status_id
        ]);

        $order->update(['status_id' => $status_id]);
    }

    /* ОБРАБОТКА КНОПКИ ORDER REPORT */
    /* ОДИНАКОВЫЙ ДЛЯ ВСЕХ ЧАТОВ */

    public function order_report(): void
    {
        $flag = $this->data->get('order_report');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        if(isset($flag)) {
            $back = $this->data->get('back');

            if(isset($back)) {
                $keyboard = $this->get_keyboard_order_card($order);
                $this->chat->replaceKeyboard($this->messageId, $keyboard)->send();
            }
        }

        if(!isset($flag)) { // редактируем текущую клаву
            $files = File::where('order_id', $order->id)
                ->where('file_type_id', 1) // фото
                ->get();

            $buttons = [];
            if($files->isNotEmpty()) {
                foreach ($files as $file) {
                    $buttons[] = Button::make($file->status->signature_photo)
                        ->url(Storage::url($file->path));
                }
            }
            $buttons[] = Button::make('Back')
                ->action('order_report')
                ->param('order_report', 1)
                ->param('back', 1)
                ->param('order_id', $order->id);
            $keyboard = Keyboard::make()->buttons($buttons);
            $this->chat->replaceKeyboard($this->messageId, $keyboard)->send();
        }
    }
}
