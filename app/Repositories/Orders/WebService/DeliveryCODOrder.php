<?php

namespace App\Repositories\Orders\WebService;

use App\Repositories\Orders\WebServiceOrderRepository;
use App\Traits\Expedition\HasDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends WebServiceOrderRepository
{
    use HasCOD;
    use HasDelivery;
}
