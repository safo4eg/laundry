<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Courier;
use App\Http\Webhooks\Handlers\User;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\OrderMessage;
use App\Services\FakeRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderMessageObserver
{
    /**
     * Handle the OrderMessage "created" event.
     *
     * @param  \App\Models\OrderMessage  $orderMessage
     * @return void
     */
    public function creating(OrderMessage $orderMessage)
    {
        $orderMessage->created_at = Carbon::now();
    }

    public function created(OrderMessage $orderMessage)
    {
        $bot = Bot::where('username', 'rastan_telegraph_bot')->first();
        $order = $orderMessage->order;

        /* фейковые данные кнопки одинаковые, т.к методы похожи */
        $fake_dataset = [
            'action' => 'order_dialogue',
            'params' => [
                'dialogue' => 1,
                'get' => 1,
                'order_id' => $order->id
            ]
        ];

        /* если в таблице чатов есть такой чат => написал курьер, иначе юзер */
        if(Chat::where('chat_id', $orderMessage->sender_chat_id)->first()) { // написал курьер => отправка юзеру
            $chat = Chat::factory()->make([
                'chat_id' => $order->user->chat_id,
                'name' => 'User',
                'telegraph_bot_id' => 1
            ]);

            $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
            (new User($order->user))->handle($fake_request, $bot);
        } else { // написал клиент
            $chat = Chat::where('laundry_id', $order->laundry_id)
                ->where('name', 'Courier')
                ->first(); // получаем чат курьера к которому относится этот заказ

            $fake_request = FakeRequest::callback_query($chat, $bot, $fake_dataset);
            (new Courier())->handle($fake_request, $bot);
        }

    }

}
