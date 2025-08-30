<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends LocalOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
