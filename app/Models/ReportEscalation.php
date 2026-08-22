<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_id', 'escalated_by', 'receiving_office', 'reason', 'notes', 'status', 'external_reference', 'escalated_at', 'resolved_at'])]
class ReportEscalation extends Model
{
    protected function casts(): array
    {
        return ['escalated_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }

    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function escalator(): BelongsTo { return $this->belongsTo(User::class, 'escalated_by'); }
}
