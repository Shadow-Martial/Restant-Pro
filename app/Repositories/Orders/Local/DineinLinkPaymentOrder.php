<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasDineIn;
use App\Traits\Payments\HasLinkPayment;

class DineinLinkPaymentOrder extends LocalOrderRepository
{
    use HasDineIn;
    use HasLinkPayment;
}
