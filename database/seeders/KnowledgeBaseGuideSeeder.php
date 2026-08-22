<?php

namespace Database\Seeders;

use App\Enums\ReportCategory;
use App\Models\KnowledgeBaseGuide;
use Illuminate\Database\Seeder;

class KnowledgeBaseGuideSeeder extends Seeder
{
    public function run(): void
    {
        $guides = [
            ReportCategory::WasteManagement->value => 'For a small household waste concern, secure loose waste, separate recyclables when possible, and place bags at the barangay collection point or on the scheduled collection day. Report blocked or overflowing collection areas for barangay action.',
            ReportCategory::Others->value => 'For a low-risk community concern, keep people away from the affected spot when practical, document any changes, and follow existing barangay procedures. If the situation becomes unsafe, update the report or mark it as an emergency.',
        ];

        foreach ($guides as $category => $text) {
            KnowledgeBaseGuide::updateOrCreate(['category' => $category], ['guide_text' => $text]);
        }
    }
}
