<?php

namespace App\Observers;

use App\Models\Chat;
use App\Models\TicketStatusPivot;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class TicketStatusObserver
{
    public function created(TicketStatusPivot $ticket_status): void
    {
        $ticket = $ticket_status->ticket;

        if ($ticket->status_id == 2) {
            $view = view('bot.support.ticket_card', [
                'ticket' => $ticket,
                'baseUrl' => url()
            ]);

            $keyboard = Keyboard::make()->buttons([
                Button::make('Ответить')->action('answer')->param('ticket_id', $ticket->id),
                Button::make('Отклонить')->action('reject')->param('ticket_id', $ticket->id) // сделать подтверждение об отклонении заявки
            ]);

            $chat = Chat::where('name', 'Support')->first();
            $chat->message(preg_replace('#^\s+#m', '', $view))
                ->keyboard($keyboard)
                ->send();
        }

        if ($ticket->status_id === 4) {
            // удаляем карточку тикета из чата поддержки и кидаем в архивный чат
            $view = view('bot.support.ticket_card', [
                'ticket' => $ticket,
                'baseUrl' => url()
            ]);

            $chat = Chat::where('name', 'Archive')->first();
            $keyboard = Keyboard::make()->buttons([
                Button::make('Восстановить')
                    ->action('return_ticket')
                    ->param('ticket_id', $ticket->id),
            ]);

            $chat->message(preg_replace('#^\s+#m', '', $view))
                ->keyboard($keyboard)
                ->send();
        }
    }
}
