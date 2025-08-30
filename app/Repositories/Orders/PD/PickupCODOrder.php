<?php

namespace App\Repositories\Orders\PD;

use App\Repositories\Orders\PDOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasCOD;

class PickupCODOrder extends PDOrderRepository
{
    use HasCOD;
    use HasPickup;
}
