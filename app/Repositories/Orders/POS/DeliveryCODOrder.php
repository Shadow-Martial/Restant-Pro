<?php

namespace App\Repositories\Orders\POS;

use App\Repositories\Orders\POSOrderRepository;
use App\Traits\Expedition\HasDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends POSOrderRepository
{
    use HasCOD;
    use HasDelivery;
}
