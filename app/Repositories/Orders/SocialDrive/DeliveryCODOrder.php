<?php

namespace App\Repositories\Orders\SocialDrive;

use App\Repositories\Orders\SocialDriveOrderRepository;
use App\Traits\Expedition\HasComplexDelivery;
use App\Traits\Payments\HasCOD;

class DeliveryCODOrder extends SocialDriveOrderRepository
{
    use HasCOD;
    use HasComplexDelivery;
}
