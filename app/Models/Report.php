<?php

namespace App\Models;

use App\Enums\ReportCategory;
use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No HasFactory yet — add one with `php artisan make:factory ReportFactory`
// once you're ready to seed test data or write feature tests.
#[Fillable([
    'user_id',
    'purok_id',
    'street_id',
    'description',
    'resident_urgency',
    'photo_path',
    'latitude',
    'longitude',
    'category',
    'priority',
    'status',
    'classified_by_svm',
    'emergency_override',
    'emergency_reason',
    'emergency_triggered_at',
    'resolved_at',
])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'priority' => ReportPriority::class,
            'status' => ReportStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'classified_by_svm' => 'boolean',
            'emergency_override' => 'boolean',
            'emergency_triggered_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function street(): BelongsTo { return $this->belongsTo(Street::class); }

    public function satisfactions(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Satisfaction::class); }

    public function escalations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ReportEscalation::class); }

    public function scopeLowPriority(Builder $query): Builder
    {
        return $query->where('priority', ReportPriority::Low);
    }

    public function scopeAwaitingClassification(Builder $query): Builder
    {
        return $query->where('classified_by_svm', false);
    }
}
