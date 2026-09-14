<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhatsAppPackage extends Model
{
    protected $table = 'whatsapp_packages';

    protected $fillable = ['slug', 'label', 'quota', 'amount', 'is_active'];

    protected $casts = [
        'quota' => 'integer',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
