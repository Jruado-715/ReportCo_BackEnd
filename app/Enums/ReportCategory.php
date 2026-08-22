<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportCategory: string
{
    case RoadDamage = 'road_damage';
    case Flooding = 'flooding';
    case WasteManagement = 'waste_management';
    case ElectricalHazard = 'electrical_hazard';
    case PublicSafety = 'public_safety';
    case Others = 'others';
}
