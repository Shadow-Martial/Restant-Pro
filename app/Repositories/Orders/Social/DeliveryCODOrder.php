<?php

namespace App\Repositories\Orders\Social;

use App\Repositories\Orders\SocialOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends SocialOrderRepository
{
    use HasCOD;
    use HasSimpleDelivery;
}
