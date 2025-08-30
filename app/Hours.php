<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hours extends Model
{
    protected $table = 'hours';

    public function restorant(): BelongsTo
    {
        return $this->belongsTo(\App\Restorant::class);
    }
}
