<?php

namespace App\Observers;

use App\Http\Webhooks\Handlers\Courier;
use App\Http\Webhooks\Handlers\Washer;
use App\Models\Bot;
use App\Models\Chat;
use App\Models\Laundry;
use App\Models\OrderServicePivot;
use App\Models\OrderStatusPivot;
use App\Models\ChatOrderPivot;
use App\Http\Webhooks\Handlers\Manager;
use App\Services\FakeRequest;
use App\Services\Helper;
use Carbon\Carbon;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use function Webmozart\Assert\Tests\StaticAnalysis\null;

class OrderStatusObserver
{
    public function created(OrderStatusPivot $orderStatus)
    {
        $order = $orderStatus->order;
        $update_order_dataset = null;
        $bot = null;

        if($order->status_id !== 1) {
            $bot = Bot::where('username', 'rastan_telegraph_bot')->first();
            $update_order_dataset = [
                'action' => 'update_order_card',
                'params' => [
                    'update_order_card' => 1,
                    'order_id' => $order->id
                ]
            ];

            if($order->status_id !== 3) {
                $manager_chat = Chat::where('name', 'Manager')->first();
                $manager_chat_request = FakeRequest::callback_query($manager_chat, $bot, $update_order_dataset);
                (new Manager())->handle($manager_chat_request, $bot);
            }
        }

        if($order->status_id === 3 OR $order->status_id === 9 OR $order->status_id === 10) { // отправка карточки заказа в чат курьеров
            $courier_chat = Chat::where('name', 'Courier')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $courier_chat_request = FakeRequest::callback_query($courier_chat, $bot, $update_order_dataset);
            (new Courier())->handle($courier_chat_request, $bot);
        }

        if($order->status_id === 5 OR $order->status_id === 10) { // отправка уведомления клиенту
            if($order->status_id === 5) {
                $status = $order->statuses()->where('id', 5)->first();
                $picked_time = (new Carbon($status->pivot->created_at))->format('Y-m-d H:i');
                $user_chat_dataset = [
                    'order' => $order,
                    'picked_time' => $picked_time
                ];
                Helper::send_user_notification($order->user, 'order_pickuped', $user_chat_dataset);
            }

            if($order->status_id === 10) {
                $order_services = OrderServicePivot::where('order_id', $order->id)->get();
                $services_info = ['total_price' => 0, 'services' => []];

                foreach ($order_services as $order_service) {
                    $services_info['services'][$order_service->service->id] = [];
                    $services_info['services'][$order_service->service->id]['title'] = $order_service->service->title;
                    $services_info['services'][$order_service->service->id]['amount'] = $order_service->amount;
                    $services_info['services'][$order_service->service->id]['price'] = $order_service->amount*$order_service->service->price;
                    $services_info['total_price'] += $services_info['services'][$order_service->service->id]['price'];
                }
                Helper::send_user_notification(
                    $order->user,
                    'things_are_weighed',
                    ['services_info' => $services_info]
                );
            }
        }

        if($order->status_id === 6) { // отправка в прачку
            $washer_chat = Chat::where('name', 'Washer')
                ->where('laundry_id', $order->laundry_id)
                ->first();
            $washer_chat_request = FakeRequest::callback_query($washer_chat, $bot, $update_order_dataset);
            (new Washer())->handle($washer_chat_request, $bot);
        }

    }
}
