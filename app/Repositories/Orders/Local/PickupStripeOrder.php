<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasStripe;

class PickupStripeOrder extends LocalOrderRepository
{
    use HasPickup;
    use HasStripe;
}
