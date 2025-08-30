<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasDineIn;
use App\Traits\Payments\HasStripe;

class DineinStripeOrder extends LocalOrderRepository
{
    use HasDineIn;
    use HasStripe;
}
