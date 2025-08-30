<?php

namespace App\Repositories\Orders\WebService;

use App\Repositories\Orders\WebServiceOrderRepository;
use App\Traits\Expedition\HasDelivery;
use App\Traits\Payments\HasStripe;

class DeliveryStripeOrder extends WebServiceOrderRepository
{
    use HasDelivery;
    use HasStripe;
}
