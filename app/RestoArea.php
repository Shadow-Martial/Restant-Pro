<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestoArea extends Model
{
    public $table = 'restoareas';

    protected $fillable = [
        'name', 'restaurant_id',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(\App\Tables::class, 'restoarea_id', 'id');
    }

    public function restorant(): BelongsTo
    {
        return $this->belongsTo(\App\Restorant::class, 'restaurant_id');
    }
}
