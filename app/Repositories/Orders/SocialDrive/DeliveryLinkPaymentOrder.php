<?php

namespace App\Repositories\Orders\SocialDrive;

use App\Repositories\Orders\SocialDriveOrderRepository;
use App\Traits\Expedition\HasComplexDelivery;
use App\Traits\Payments\HasLinkPayment;

class DeliveryLinkPaymentOrder extends SocialDriveOrderRepository
{
    use HasComplexDelivery;
    use HasLinkPayment;
}
