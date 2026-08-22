<?php

namespace App\Models;

use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'guide_text'])]
class KnowledgeBaseGuide extends Model
{
    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
        ];
    }
}
