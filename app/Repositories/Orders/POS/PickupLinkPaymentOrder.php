<?php

namespace App\Repositories\Orders\POS;

use App\Repositories\Orders\POSOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends POSOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
