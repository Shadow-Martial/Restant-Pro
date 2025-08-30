<?php

namespace App\Repositories\Orders\MobileApp;

use App\Repositories\Orders\MobileAppOrderRepository;
use App\Traits\Expedition\HasDelivery;
use App\Traits\Payments\HasStripe;

class DeliveryStripeOrder extends MobileAppOrderRepository
{
    use HasDelivery;
    use HasStripe;
}
