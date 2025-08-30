<?php

namespace App\Repositories\Orders\Local;

use App\Repositories\Orders\LocalOrderRepository;
use App\Traits\Expedition\HasDineIn;
use App\Traits\Payments\HasCOD;

class DineinCODOrder extends LocalOrderRepository
{
    use HasCOD;
    use HasDineIn;
}
