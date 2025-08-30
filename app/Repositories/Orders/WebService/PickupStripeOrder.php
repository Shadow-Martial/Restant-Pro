<?php

namespace App\Repositories\Orders\WebService;

use App\Repositories\Orders\WebServiceOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasStripe;

class PickupStripeOrder extends WebServiceOrderRepository
{
    use HasPickup;
    use HasStripe;
}
