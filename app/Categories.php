<?php

namespace App;

use App\Models\TranslateAwareModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Categories extends TranslateAwareModel implements Sortable
{
    use SoftDeletes;
    use SortableTrait;

    protected $table = 'categories';

    public $fillable = ['name', 'company_id', 'created_at', 'updated_at'];

    public $translatable = ['name'];

    public $sortable = [
        'order_column_name' => 'order_index',
        'sort_when_creating' => true,
    ];

    //Used for sort grouping
    public function buildSortQuery()
    {
        return static::query()->where('company_id', $this->company_id);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Items::class, 'category_id', 'id')->orderBy('items.updated_at', 'desc');
    }

    public function aitems(): HasMany
    {
        return $this->hasMany(\App\Items::class, 'category_id', 'id')->where(['items.available' => 1]);
    }

    public function restorant(): BelongsTo
    {
        return $this->belongsTo(\App\Restorant::class, 'company_id', 'id');
    }
}
