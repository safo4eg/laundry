<?php

namespace App\Http\Webhooks\Handlers;

use App\Models\Chat;
use App\Models\Order;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Stringable;


class Manager extends WebhookHandler
{
    public function send_to_couriers(): void
    {
        $choice = $this->data->get('choice');
        $order_id = $this->data->get('order_id');
        $courier_chat_id = $this->data->get('courier_chat_id');

        (Order::find($order_id))->update([
            'status_id' => 3,
            'laundry_id' => 1
        ]);

        $courier_chat = Chat::where('chat_id', $courier_chat_id)->first();
        $courier_chat->message('отправка заказа')->send();
    }

    public function qr(): void
    {
        $this->chat->photo(Storage::path("user/qr_code_1.png"))->send();
    }
}
