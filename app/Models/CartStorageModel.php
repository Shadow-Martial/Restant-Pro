<?php

namespace App\Models;

use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CartStorageModel extends Model
{
    use HasConfig;
    use HasFactory;

    protected $modelName = \App\Models\CartStorageModel::class;

    protected $table = 'cart_storage';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'cart_data', 'vendor_id', 'user_id', 'type', 'receipt_number', 'kds_finished',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Restorant::class, 'id', 'vendor_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\User::class, 'id', 'user_id');
    }

    public function setCartDataAttribute($value)
    {
        $this->attributes['cart_data'] = serialize($value);
    }

    public function getCartDataAttribute($value)
    {
        return unserialize($value);
    }

    public function updateItemAttribute($value, $itemId, $attributeName, $attributeValue)
    {
        foreach ($value as $key => $item) {
            if ($key == $itemId) {
                $value[$key][$attributeName] = $attributeValue;
            }
        }
        $this->attributes['cart_data'] = serialize($value);
    }

    public function updateAttribute($attributeName, $attributeValue)
    {
        $this->attributes[$attributeName] = $attributeValue;
    }
}
