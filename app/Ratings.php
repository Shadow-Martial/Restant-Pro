<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ratings extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Order::class);
    }
}
