<?php

namespace App\Http\Webhooks\Handlers;

use App\Models\Order;
use DefStudio\Telegraph\Handlers\WebhookHandler;

class Manager extends WebhookHandler
{
    public function send_to_couriers(): void
    {
        $choice = $this->data->get('choice');
        $order_id = $this->data->get('order_id');

        (Order::find($order_id))->update([
            'status_id' => 3,
            'laundry_id' => 1
        ]);

        switch ($choice) {
            case 1:
                $this->chat->message('отправка курьерам 1')->send();
                break;
            case 2:
                $this->chat->message('отправка курьерам 2')->send();
                break;
        }
    }
}
