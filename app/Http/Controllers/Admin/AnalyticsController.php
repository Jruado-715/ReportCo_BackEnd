<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshClusters;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function purokRanking(): JsonResponse
    {
        $q = Report::query()
            ->select('purok_id', DB::raw('COUNT(*) as report_count'))
            ->whereNotNull('purok_id');
        $this->applyReportFilters($q);
        $ranking = $q->groupBy('purok_id')->orderByDesc('report_count')->with('purok:id,name')->get();
        return response()->json(['success' => true, 'data' => $ranking]);
    }

    public function streetRanking(): JsonResponse
    {
        $q = Report::query()
            ->join('streets', 'reports.street_id', '=', 'streets.id')
            ->join('puroks', 'reports.purok_id', '=', 'puroks.id')
            ->select(
                'reports.purok_id',
                'puroks.name as purok_name',
                'reports.street_id',
                'streets.name as street_name',
                DB::raw('COUNT(reports.id) as report_count')
            )
            ->groupBy('reports.purok_id', 'puroks.name', 'reports.street_id', 'streets.name')
            ->orderByDesc('report_count');
        $this->applyReportFilters($q, 'reports.');
        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    public function summary(): JsonResponse
    {
        $filtered = Report::query();
        $this->applyReportFilters($filtered);
        $reportIds = (clone $filtered)->select('reports.id');

        $avg = DB::table('satisfactions')->whereIn('report_id', $reportIds)->avg('rating');

        $resolvedQuery = (clone $filtered)->where('status', 'resolved')->whereNotNull('resolved_at');
        $driver = $resolvedQuery->getModel()->getConnection()->getDriverName();
        $resolutionExpression = match ($driver) {
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)))',
            'mysql', 'mariadb' => 'AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at))',
            'sqlite' => 'AVG((julianday(resolved_at) - julianday(created_at)) * 86400)',
            default => null,
        };
        $resolved = $resolutionExpression === null
            ? null
            : $resolvedQuery->selectRaw($resolutionExpression . ' as seconds')->value('seconds');

        $activeQuery = (clone $filtered)->whereIn('status', ['received', 'in_progress', 'escalated']);
        $resolvedCountQuery = (clone $filtered)->where('status', 'resolved');
        $emergencyQuery = (clone $filtered)->where('emergency_override', true)->where('status', '!=', 'resolved');

        return response()->json(['success' => true, 'data' => [
            'active_reports' => $activeQuery->count(),
            'resolved_reports' => $resolvedCountQuery->count(),
            'average_satisfaction' => $avg !== null ? round((float) $avg, 2) : null,
            'average_resolution_seconds' => $resolved !== null ? (int) $resolved : null,
            'emergency_reports' => $emergencyQuery->count(),
        ]]);
    }

    public function heatmap(): JsonResponse
    {
        $clusters = Cache::get(RefreshClusters::CACHE_KEY, []);
        return response()->json(['success' => true, 'data' => $clusters, 'stale' => $clusters === []]);
    }

    public function categoryBreakdown(): JsonResponse
    {
        $q = Report::query()
            ->select('category', DB::raw('COUNT(*) as report_count'))
            ->whereNotNull('category');
        $this->applyReportFilters($q);
        $rows = $q->groupBy('category')->orderByDesc('report_count')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function statusBreakdown(): JsonResponse
    {
        $q = Report::query()->select('status', DB::raw('COUNT(*) as report_count'));
        $this->applyReportFilters($q);
        $rows = $q->groupBy('status')->orderByDesc('report_count')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Shared Admin Reports/Analytics filters: Purok, Street, Category,
     * Priority, Status, and created-date range.
     */
    private function applyReportFilters(Builder $q, string $prefix = ''): void
    {
        $r = request();
        foreach (['purok_id', 'street_id', 'category', 'priority', 'status'] as $field) {
            if ($r->filled($field)) {
                $q->where($prefix . $field, $r->input($field));
            }
        }
        if ($r->filled('from')) {
            $q->whereDate($prefix . 'created_at', '>=', $r->input('from'));
        }
        if ($r->filled('to')) {
            $q->whereDate($prefix . 'created_at', '<=', $r->input('to'));
        }
    }
}
