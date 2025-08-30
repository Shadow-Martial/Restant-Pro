<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = [
        'name', 'table_id', 'phone_number', 'email', 'note', 'restaurant_id', 'by',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(\App\Tables::class);
    }
}
