<?php

namespace App\Repositories\Orders\PD;

use App\Repositories\Orders\PDOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends PDOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
