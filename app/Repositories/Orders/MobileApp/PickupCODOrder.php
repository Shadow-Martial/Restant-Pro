<?php

namespace App\Repositories\Orders\MobileApp;

use App\Repositories\Orders\MobileAppOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasCOD;

class PickupCODOrder extends MobileAppOrderRepository
{
    use HasCOD;
    use HasPickup;
}
