<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Extra extends Model
{
    protected $fillable = ['name', 'code', 'description', 'price', 'per_leg', 'max_quantity', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'per_leg' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
