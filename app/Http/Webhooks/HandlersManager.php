<?php

namespace App\Http\Webhooks;

use App\Http\Webhooks\Handlers\User;
use DefStudio\Telegraph\DTO\CallbackQuery;
use DefStudio\Telegraph\DTO\Chat;
use DefStudio\Telegraph\DTO\Message;
use DefStudio\Telegraph\Models\TelegraphBot;
use DefStudio\Telegraph\Models\TelegraphChat;
use http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User as UserModel;

class HandlersManager
{
    private Chat $chat;
    private string $chat_handler_class_prefix;

    public function __construct()
    {
        $this->chat_handler_class_prefix = '\\App\\Http\\Webhooks\\Handlers\\';
    }

    public function handle(Request $request, TelegraphBot $bot) {
        if($request->has('message') OR $request->has('callback_query')) {

            if ($request->has('message')) {
                $this->chat = (Message::fromArray($request->input('message')))
                    ->chat();
            }

            if ($request->has('callback_query')) {
                $this->chat = (CallbackQuery::fromArray($request->input('callback_query')))
                    ->message()
                    ->chat();
            }

            if($this->chat->type() === 'supergroup' OR $this->chat->type() === 'group') {
                $chat = TelegraphChat::where('chat_id', $this->chat->id())->first();
                if($chat) {
                    // Если прилетает сообщение от левого чата
                    $handler_class = $this->chat_handler_class_prefix.$chat->name;
                    (new $handler_class())->handle($request, $bot);
                }
            }

            if($this->chat->type() === 'private') {
                $user = UserModel::where('chat_id', $this->chat->id())->first();
                (new User($user))->handle($request, $bot);
            }
        } else {
            // если прилетают данные кроме Message и CallbackQuery
            return response(null, 204);
        }

    }
}
