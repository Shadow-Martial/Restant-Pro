<?php

namespace App\Repositories\Orders\WebService;

use App\Repositories\Orders\WebServiceOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends WebServiceOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
