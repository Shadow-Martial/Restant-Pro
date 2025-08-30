<?php

namespace App\Listeners;

use App\Restorant;

class SetRestaurantIdInSession
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $vendor = Restorant::where('user_id', $event->user->id)->first();
        if ($vendor) {
            session(['restaurant_id' => $vendor->id]);
            session(['restaurant_currency' => $vendor->currency]);
            session(['restaurant_convertion' => $vendor->do_covertion]);
        }

    }
}
