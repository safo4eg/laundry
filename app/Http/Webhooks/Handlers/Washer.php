<?php

namespace App\Http\Webhooks\Handlers;
use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\ChatOrderPivot;
use App\Models\File;
use App\Models\Order;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;

class Washer extends WebhookHandler
{
    /* На этом этапе функционал кнопки добавления фото одинаковый у всех */
    /* так же функционал второй кнопки с ордер репортом тоже одинаковый */

    use ChatsHelperTrait;
    public function __construct()
    {
        $this->buttons = config('buttons.washer');
        $this->general_buttons = config('buttons.chats');
        $this->template_prefix = 'bot.chats.';
        parent::__construct();
    }
    public function send_order_card(Order $order): void
    {
        if(in_array($order->status_id, [6, 7, 8])) {
            $keyboard = Keyboard::make();
            if($order->status_id === 8) {
                $keyboard->button($this->buttons[$order->status_id])
                    ->action('send_for_weighing')
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

    /* без флагов т.к ничего не показывает только обрабатывает действие */

    public function send_for_weighing(): void
    {
        $order_id = $this->data->get('order_id');
        $order = Order::where('id', $order_id)->first();
        $order->update(['status_id' => ++$order->status_id]);
        $this->delete_order_card_messages($order, true);
    }

    public function show_card(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('show_card');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) {
            $this->delete_message_by_types([5, 6, 7]);
            $this->request_photo($order); // сообщение с просьбой отправить фото
        }

        if(!isset($flag)) {
            $template = $this->template_prefix.'order_info';
            $photo = File::where('order_id', $order->id)
                ->where('file_type_id', 1)
                ->where('order_status_id', $order->status_id)
                ->first();

            $response = $this->chat
                ->photo(Storage::path($photo->path))
                ->message(view($template, ['order' => $order]))
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
