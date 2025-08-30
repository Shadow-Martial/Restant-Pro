<?php

namespace App\Repositories\Orders\POS;

use App\Repositories\Orders\POSOrderRepository;
use App\Traits\Expedition\HasDineIn;
use App\Traits\Payments\HasCOD;

class DineinCODOrder extends POSOrderRepository
{
    use HasCOD;
    use HasDineIn;
}
