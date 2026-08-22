<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'barangay_id'])]
class Purok extends Model
{
    public function barangay(): BelongsTo { return $this->belongsTo(Barangay::class); }
    public function residents(): HasMany { return $this->hasMany(User::class); }
    public function reports(): HasMany { return $this->hasMany(Report::class); }
    public function streets(): HasMany { return $this->hasMany(Street::class); }
}
