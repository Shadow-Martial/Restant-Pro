<?php

namespace App\Repositories\Orders\PD;

use App\Repositories\Orders\PDOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasLinkPayment;

class DeliveryLinkPaymentOrder extends PDOrderRepository
{
    use HasLinkPayment;
    use HasSimpleDelivery;
}
