<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends LocalOrderRepository
{
    use HasCOD;
    use HasSimpleDelivery;
}
