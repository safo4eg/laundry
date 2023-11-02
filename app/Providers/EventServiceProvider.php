<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderStatusPivot;
use App\Models\Ticket;
use App\Models\TicketStatusPivot;
use App\Observers\OrderObserver;
use App\Observers\OrderStatusObserver;
use App\Observers\TicketObserver;
use App\Observers\TicketStatusObserver;
use App\Models\Payment;
use App\Observers\OrderMessageObserver;
use App\Observers\PaymentObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Order::observe(OrderObserver::class);
        OrderStatusPivot::observe(OrderStatusObserver::class);
        Ticket::observe(TicketObserver::class);
        TicketStatusPivot::observe(TicketStatusObserver::class);
        OrderMessage::observe(OrderMessageObserver::class);
        Payment::observe(PaymentObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
