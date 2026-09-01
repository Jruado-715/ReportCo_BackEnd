<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'report_id', 'announcement_id', 'type', 'title', 'message', 'read_at'])]
class ReportNotification extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function announcement(): BelongsTo { return $this->belongsTo(Announcement::class); }
}
