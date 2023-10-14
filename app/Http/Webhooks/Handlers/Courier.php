<?php

namespace App\Http\Webhooks\Handlers;
use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\Chat;
use App\Models\ChatOrder;
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
        $this->config = config('buttons.courier');
        $this->template_prefix = 'bot.courier.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void
    {
        $keyboard = $this->get_current_order_card_keyboard($order);
        switch ($order->status_id) {
            case 3:
                $this->pickup($order, $keyboard);
                break;
            case 5:
                $this->deliver_in_laundry($order, $keyboard);
                break;
        }
    }

    public function get_current_order_card_keyboard(Order $order): Keyboard|null
    {
        $keyboard = null;

        if($order->status_id == 4) {
            $buttons_texts = $this->config['pickup'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['pickup'])
                    ->action('pickup')
                    ->param('pickup', 1)
                    ->param('order_id', $order->id)
            ]);
        } else if($order->status_id == 5) {
            $buttons_texts = $this->config['deliver_in_laundry'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['deliver'])
                    ->action('deliver_in_laundry')
                    ->param('deliver_in_laundry', 1)
                    ->param('order_id', $order->id)
            ]);
        }

        return $keyboard;
    }

    public function pickup(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('pickup');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) { // Обработка данных с кнопки
            $this->delete_message_by_types([5, 6, 7]);
            $this->request_photo($order); // сообщение с просьбой отправить фото
        }

        if(!isset($flag)) { // отображения карточки с кнопками
            $template = $this->template_prefix.'order_info';
            $this->chat
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
        }
    }

    public function deliver_in_laundry(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('deliver_in_laundry');

        if(isset($flag)) {

        }

        if(!isset($flag)) {
            $main_chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 1)
                ->first();
            $template = $this->template_prefix.'order_info';
            $photos = File::where('order_id', $order->id)
                ->where('file_type_id', 1)
                ->where('order_status_id', 5)
                ->get();

            $first_photo = $photos->first();
            $this->chat->deleteMessage($main_chat_order->message_id)->send();
            $response = $this->chat->photo(Storage::path($first_photo->path))
                ->html(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();

            $main_chat_order = ChatOrder::create([
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

        // *** Еще проверку на то, что есть просьба отправить фото!
        if(isset($photos) AND $photos->isNotEmpty()) { // обработка прилетевших фотографий
            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => null,
                'message_id' => $this->messageId,
                'message_type_id' => 8
            ]);

            $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->whereIn('message_type_id', [5, 6, 7])
                ->first();

            $order = isset($chat_order)? $chat_order->order: null;
            $photo = $this->save_photo($photos, $order);

            $message_timestamp = $this->message->date()->timestamp; // время отправки прилетевшего фото
            $last_message_timestamp = $this->chat->storage()->get('photo_message_timestamp'); // timestamp предыдущего прилетевшего фото

            if(isset($chat_order)) {
                if($chat_order->message_type_id === 5) {
                    $this->delete_message_by_types([5, 8]);
                    $this->confirm_photo($photo, $chat_order->order);
                } else {
                    if($message_timestamp === $last_message_timestamp) {
                        if($chat_order->message_type_id === 6 OR $chat_order->message_type_id === 7) {
                            $this->delete_message_by_types([8]);
                        }
                    } else {
                        $this->delete_message_by_types([6, 7, 8]);
                        $this->select_order($photo);
                    }
                }
            } else {
                $this->delete_message_by_types([8]);
                $this->select_order($photo);
            }

            $this->chat->storage()->set('photo_message_timestamp', $message_timestamp);

        }
    }
}
