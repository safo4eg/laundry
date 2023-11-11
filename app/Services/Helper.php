<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;

class Helper
{
    public static function send_user_custom_notification($user, string $template, string $photo_path, Keyboard $keyboard): void
    {
        $chat = Chat::factory()->make([
            'chat_id' => $user->chat_id,
            'name' => 'User',
            'telegraph_bot_id' => 1
        ]);

        if(isset($user->message_id)) {
            $chat->deleteMessage($user->message_id)->send();
        }

        $response = null;
        if(isset($photo_path)) {
            $response = $chat
                ->photo(Storage::path($photo_path))
                ->html($template)
                ->keyboard($keyboard)
                ->send();
        } else {
            $response = $chat
                ->message($template)
                ->keyboard($keyboard)
                ->send();
        }

        $user->update([
            'page' => 'notification',
            'message_id' => $response->telegraphMessageId()
        ]);
    }
    public static function send_user_notification($user, string $template, array $dataset = null, Keyboard $keyboard = null): void
    {
        $language_code = $user->language_code;
        $chat_id = $user->chat_id;

        $template = "bot.user.{$language_code}.notifications.{$template}";

        $chat = Chat::factory()->make([
            'chat_id' => $chat_id,
            'name' => 'User',
            'telegraph_bot_id' => 1
        ]);

        $dataset = isset($dataset)? $dataset: [];

        if (!$keyboard){
            $chat->message(view($template, $dataset))->send();
        } else {
            $chat->message(view($template, $dataset))->keyboard($keyboard)->send();
        }
    }

    public static function get_price(array $order_services): array|null
    {
        if(!isset($order_services)) return null;
        else {
            $services = Service::whereIn('id', $order_services['selected'])->get();
            $price = ['sum' => 0, 'services' => []];
            foreach ($services as $service) {
                $price['services'][$service->id] = [];
                $price['services'][$service->id]['amount'] = $order_services[$service->id];
                $price['services'][$service->id]['price'] = $price['services'][$service->id]['amount']*$service->price;
                $price['services'][$service->id]['title'] = $service->title;
                $price['sum'] += $price['services'][$service->id]['price'];
            }

            if($price['sum'] < 240000) $price['sum'] = 240000;

            return $price;
        }
    }

}
