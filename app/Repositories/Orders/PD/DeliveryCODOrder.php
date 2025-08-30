<?php

namespace App\Repositories\Orders\PD;

use App\Repositories\Orders\PDOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends PDOrderRepository
{
    use HasCOD;
    use HasSimpleDelivery;
}
