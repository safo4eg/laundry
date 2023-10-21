<?php

namespace App\Http\Webhooks\Handlers;
use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\Order;
use App\Models\File;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Session\Store;
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
        if(in_array($order->status_id, [3, 5, 9, 10])) {
            $keyboard = Keyboard::make();

            if($order->status_id === 9) { // взвешивание
                $keyboard->button($this->buttons[$order->status_id])
                    ->action('weigh')
                    ->param('order_id', $order->id);
            } else {
                $keyboard->button($this->buttons[$order->status_id])
                    ->action('show_card')
                    ->param('show_card', 1)
                    ->param('order_id', $order->id);
            }

            $keyboard->button($this->general_buttons['report'])
                ->action('test');
            $this->show_card($order, $keyboard);
        }
    }

    public function weigh(): void
    {
        $flag = $this->data->get('weight');
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();

        if(isset($flag)) { // обработка
            // галочка у кнопки
            // просьба ввести количество
        }

        if(!isset($flag)) { // просьба взвешать вещи

        }
    }

    public function show_card(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('show_card');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) {
            $this->delete_message_by_types([5, 6, 7]);
            $this->request_photo($order);
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
        $message_text = $this->message->text(); // обычный текст

        if(isset($message_text) AND $photos->isEmpty()) { // просто текст
            $this->chat->message('просто текст')->send();
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
}
