<?php

namespace App\Repositories\Orders\Social;

use App\Repositories\Orders\SocialOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasLinkPayment;

class DeliveryLinkPaymentOrder extends SocialOrderRepository
{
    use HasLinkPayment;
    use HasSimpleDelivery;
}
