<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['water_level', 'threshold_crossed', 'recorded_at'])]
class IotReading extends Model
{
    protected function casts(): array
    {
        return [
            'water_level' => 'decimal:2',
            'threshold_crossed' => 'boolean',
            'recorded_at' => 'immutable_datetime',
        ];
    }
}
