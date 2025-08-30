<?php

namespace App\Repositories\Orders\Social;

use App\Repositories\Orders\SocialOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasLinkPayment;

class PickupLinkPaymentOrder extends SocialOrderRepository
{
    use HasLinkPayment;
    use HasPickup;
}
