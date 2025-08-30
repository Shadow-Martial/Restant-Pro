<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Variants extends Model
{
    use SoftDeletes;

    protected $table = 'variants';

    protected $fillable = ['price', 'item_id', 'options', 'is_system'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Items::class);
    }

    public function getOptionsListAttribute()
    {
        return implode(',', is_array(json_decode($this->options, true)) ? json_decode($this->options, true) : [__('No options values selected')]);
    }

    public function extras(): BelongsToMany
    {
        return $this->belongsToMany(\App\Extras::class, 'variants_has_extras', 'variant_id', 'extra_id');
    }
}
