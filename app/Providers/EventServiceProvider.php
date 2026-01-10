<?php

namespace App\Providers;

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
        
        // Booking Events
        \App\Events\BookingCreated::class => [
            \App\Listeners\SendBookingCreatedNotification::class,
        ],
        \App\Events\BookingStatusChanged::class => [
            \App\Listeners\SendBookingStatusChangedNotification::class,
        ],
        \App\Events\ManagerAssigned::class => [
            \App\Listeners\SendManagerAssignedNotification::class,
        ],
        
        // Message Events
        \App\Events\NewMessageReceived::class => [
            \App\Listeners\SendNewMessageNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
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
