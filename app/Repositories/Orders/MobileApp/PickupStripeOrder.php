<?php

namespace App\Repositories\Orders\MobileApp;

use App\Repositories\Orders\MobileAppOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasStripe;

class PickupStripeOrder extends MobileAppOrderRepository
{
    use HasPickup;
    use HasStripe;
}
