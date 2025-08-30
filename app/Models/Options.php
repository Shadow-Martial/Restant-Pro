<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Options extends TranslateAwareModel
{
    use SoftDeletes;

    protected $table = 'options';

    protected $fillable = ['name', 'options', 'item_id'];

    public $translatable = ['name'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Items::class);
    }
}
