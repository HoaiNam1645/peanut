<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerApp extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'auth_url',
        'proxy_status',
        'status',
    ];

    public function partnerStores(): HasMany
    {
        return $this->hasMany(PartnerStore::class);
    }
}
