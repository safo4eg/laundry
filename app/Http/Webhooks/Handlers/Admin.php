<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\File;
use App\Services\FakeRequest;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use App\Models\Order;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;

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
}
