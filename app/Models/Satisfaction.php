<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_id', 'user_id', 'rating', 'comment'])]
class Satisfaction extends Model
{
    public function report(): BelongsTo { return $this->belongsTo(Report::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
