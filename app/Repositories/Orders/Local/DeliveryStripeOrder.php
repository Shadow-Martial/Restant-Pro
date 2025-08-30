<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasSimpleDelivery;
use App\Traits\Payments\HasStripe;

class DeliveryStripeOrder extends LocalOrderRepository
{
    use HasSimpleDelivery;
    use HasStripe;
}
