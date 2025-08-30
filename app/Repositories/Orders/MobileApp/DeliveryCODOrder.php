<?php

namespace App\Repositories\Orders\MobileApp;

use App\Repositories\Orders\MobileAppOrderRepository;
use App\Traits\Expedition\HasDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends MobileAppOrderRepository
{
    use HasCOD;
    use HasDelivery;
}
