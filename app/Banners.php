<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Banners extends MyModel
{
    protected $table = 'banners';

    protected $fillable = [
        'name', 'img', 'type', 'vendor_id', 'page_id', 'active_from', 'active_to',
    ];

    protected $appends = ['imgm'];

    protected $imagePath = '/uploads/banners/';

    public function getImgmAttribute()
    {
        return $this->getImge($this->img, config('global.restorant_details_image'), '_banner.jpg');
    }

    public function restaurant(): HasOne
    {
        return $this->hasOne(\App\Restorant::class, 'id', 'vendor_id');
    }

    public function page(): HasOne
    {
        return $this->hasOne(\App\Pages::class, 'id', 'page_id');
    }
}
