<?php

namespace App\Repositories\Orders\POS;

use App\Repositories\Orders\POSOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasCOD;

class PickupCODOrder extends POSOrderRepository
{
    use HasCOD;
    use HasPickup;
}
