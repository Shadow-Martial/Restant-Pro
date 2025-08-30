<?php

namespace App\Repositories\Orders\WebService;

use App\Repositories\Orders\WebServiceOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasCOD;

class PickupCODOrder extends WebServiceOrderRepository
{
    use HasCOD;
    use HasPickup;
}
