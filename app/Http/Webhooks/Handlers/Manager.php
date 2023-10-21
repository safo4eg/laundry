<?php

namespace App\Http\Webhooks\Handlers;

use App\Http\Webhooks\Handlers\Traits\ChatsHelperTrait;
use App\Models\Chat;
use App\Models\ChatOrderPivot;
use App\Models\File;
use App\Models\Laundry;
use App\Models\Order;
use App\Services\FakeRequest;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\Tests\Functional\LocalDataTest;


class Manager extends WebhookHandler
{
    use ChatsHelperTrait;

    public function __construct()
    {
        $this->config = config('buttons.manager');
        $this->template_prefix = 'bot.chats.';
        parent::__construct();
    }

    public function send_order_card(Order $order): void // распределение какую карточку отправить
    {
        $keyboard = Keyboard::make();
        if($order->status_id === 2) {
            $keyboard = Keyboard::make();
            $laundries = Laundry::all();
            foreach ($laundries as $laundry)
            {
                $keyboard
                    ->button($laundry->title)
                    ->action('distribute')
                    ->param('distribute', 1)
                    ->param('laundry_id', $laundry->id)
                    ->param('order_id', $order->id);
            }

            $this->distribute($order, $keyboard);
        } else {
            $keyboard->buttons([
                Button::make('тестовая кнопка 1')->action('show_card'),
                Button::make('тестовая кнопка 2')->action('show_card')
            ]);
            $this->show_card($order, $keyboard);
        }
    }

    public function distribute(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('distribute');
        $order_id = $this->data->get('order_id');
        $order = isset($order)? $order: Order::find($order_id);

        if(isset($flag)) {
            $laundry_id = $this->data->get('laundry_id');
            $order->update([
                'status_id' => 3,
                'laundry_id' => $laundry_id
            ]);

            $this->update_order_card($order);
        }

        if(!isset($flag)) {
            $template = $this->template_prefix.'order_info';
            $response = $this->chat
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

    public function show_card(Order $order = null, Keyboard $keyboard = null): void
    {
        $flag = $this->data->get('show_card');

        if(isset($flag)) {

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

}
