<?php

namespace App\Repositories\Orders\MobileApp;

use App\Repositories\Orders\MobileAppOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends MobileAppOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
