<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purok_id', 'name'])]
class Street extends Model
{
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
