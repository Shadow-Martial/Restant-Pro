<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasLinkPayment;

class DeliveryLinkPaymentOrder extends LocalOrderRepository
{
    use HasLinkPayment;
    use HasSimpleDelivery;
}
