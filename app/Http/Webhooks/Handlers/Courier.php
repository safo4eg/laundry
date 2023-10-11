<?php

namespace App\Http\Webhooks\Handlers;
use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\Chat;
use App\Models\ChatOrder;
use App\Models\Order;
use DefStudio\Telegraph\Facades\Telegraph;
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
        $this->config = config('buttons.courier');
        $this->template_prefix = 'bot.courier.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void // ообязательный метод(должен вообще быть у родителя абстрактным)
    {
        switch ($order->status_id) {
            case 3:
                $this->pickup($order);
                break;
        }
    }

    public function get_current_order_card_keyboard(Order $order): Keyboard|null
    {
        $keyboard = null;

        if($order->status_id == 5) {
            $buttons_texts = $this->config['pickup'];
            $keyboard = Keyboard::make()->buttons([
                Button::make($buttons_texts['pickup'])
                    ->action('pickup')
                    ->param('pickup', 1)
                    ->param('order_id', $order->id)
            ]);
        }

        return $keyboard;
    }

    public function pickup(Order $order = null): void
    {
        $flag = $this->data->get('pickup');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) { // Обработка данных с кнопки
            $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('order_id', $order->id)
                ->where('message_type_id', 5)
                ->first();

            if(isset($chat_order)) {
                $this->chat->deleteMessage($chat_order->message_id)->send();
                $chat_order->delete();
            }

            $template = $this->template_prefix.'photo_request';
            $button_text = $this->config['photo_request']['cancel'];
            $response = $this->chat
                ->message(view($template, ['order' => $order]))
                ->keyboard(Keyboard::make()->buttons([
                    Button::make($button_text)->action('test')
                ]))->send();

            ChatOrder::create([
                'telegraph_chat_id' => $this->chat->id,
                'order_id' => $order->id,
                'message_id' => $response->telegraphMessageId(),
                'message_type_id' => 5
            ]);

            $this->chat->storage()->set('order', $order);
        }

        if(!isset($flag)) { // отображения карточки с кнопками
            $template = $this->template_prefix.'order_info';
            $keyboard = $this->get_current_order_card_keyboard($order);
            $this->chat
                ->message(view($template, ['order' => $order]))
                ->keyboard($keyboard)
                ->send();
        }
    }

    protected function handleChatMessage(Stringable $text): void
    {
        $photos = $this->message->photos();

        // *** Еще проверку на то, что есть просьба отправить фото!
        if(isset($photos) AND $photos->isNotEmpty()) { // обработка прилетевших фотографий
            $chat_order = ChatOrder::where('telegraph_chat_id', $this->chat->id)
                ->where('message_type_id', 5)
                ->first();
            $photo = $this->save_photo($photos, $chat_order);

            $message_timestamp = $this->message->date()->timestamp; // timestamp отправки текущего фото
            $last_message_timestamp = $this->chat->storage()->get('photo_message_timestamp'); // timestamp последнего прилетевшего фото
            if(is_null($last_message_timestamp) OR $message_timestamp !== $last_message_timestamp) {
                    $this->confirm_photo($photo, $chat_order);
            }


            $this->chat->storage()->set('photo_message_timestamp', $message_timestamp);
//            if(isset($last_message_timestamp) AND $message_timestamp === $last_message_timestamp) { // проверка были ли они в одном сообщении
//                $this->chat->message('отправка нескольких фото подряд')->send();
//            } else {
//                $this->chat->message('отправка одного фото')->send();
//            }
//            $photo = $photos->last(); // получаем лучшее по качеству изображение

        }
    }
}
