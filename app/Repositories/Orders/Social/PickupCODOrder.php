<?php

namespace App\Repositories\Orders\Social;

use App\Repositories\Orders\SocialOrderRepository;
use App\Traits\Expedition\HasPickup;
use App\Traits\Payments\HasCOD;

class PickupCODOrder extends SocialOrderRepository
{
    use HasCOD;
    use HasPickup;
}
