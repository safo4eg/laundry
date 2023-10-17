<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\TicketStatusPivot;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;

class TicketStatusObserver
{
    public function created(TicketStatusPivot $ticket_status): void
    {
        $ticket = $ticket_status->ticket;

        if ($ticket->status_id == 2){
            $view = view('bot.support.ticket_card', [
                'ticket' => $ticket
            ]);

            $keyboard = Keyboard::make()->buttons([
                Button::make('Ответить')->action('start')
            ]);

            $chat = Chat::where('name', 'Support')->first();
            $chat->message(preg_replace('#^\s+#m', '', $view))
                ->keyboard($keyboard)
                ->send();
        }
    }
}
