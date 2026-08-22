<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ClassifyReport;
use App\Jobs\SendPushNotification;
use App\Models\Report;
use App\Models\Satisfaction;
use App\Models\KnowledgeBaseGuide;
use App\Models\Street;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\ReportNotificationService;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required','string','max:2000'],
            'resident_urgency' => ['required','in:normal,important,urgent,emergency'],
            'photo' => ['nullable','image','max:5120'],
            'latitude' => ['required','numeric','between:-90,90'],
            'longitude' => ['required','numeric','between:-180,180'],
            'purok_id' => ['required','exists:puroks,id'],
            'street_id' => ['nullable','exists:streets,id'],
        ]);

        if (!empty($validated['street_id'])) {
            $street = Street::findOrFail($validated['street_id']);
            abort_unless((int)$street->purok_id === (int)$validated['purok_id'], 422, 'The selected street does not belong to the selected purok.');
        }

        $photoPath = $request->hasFile('photo') ? $request->file('photo')->store('reports','public') : null;

        $report = DB::transaction(function () use ($validated, $photoPath, $request): Report {
            return Report::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'photo_path' => $photoPath,
                'status' => ReportStatus::Received,
                'priority' => $validated['resident_urgency'] === 'emergency' ? ReportPriority::Emergency : ReportPriority::Unclassified,
                'resident_urgency' => $validated['resident_urgency'],
                'emergency_override' => $validated['resident_urgency'] === 'emergency',
                'emergency_reason' => $validated['resident_urgency'] === 'emergency' ? 'Resident marked this report as an emergency.' : null,
                'emergency_triggered_at' => $validated['resident_urgency'] === 'emergency' ? now() : null,
            ]);
        });

        app(ReportNotificationService::class)->send($request->user(), 'Report received', 'Your report #'.$report->id.' has been received and is pending review.', $report, 'report_received');
        SendPushNotification::dispatch($request->user(), 'Report received', 'Your report #'.$report->id.' has been received.');
        ClassifyReport::dispatch($report)->afterCommit();
        if ($report->emergency_override) { app(ReportNotificationService::class)->send($request->user(), 'Emergency report submitted', 'Report #'.$report->id.' was marked as an emergency and needs urgent barangay attention.', $report, 'emergency'); }

        return response()->json(['success'=>true,'data'=>$report->load('purok','street'),'message'=>'Report received and is being reviewed.'], 202);
    }

    public function photo(Request $request, Report $report)
    {
        $user = $request->user();
        $isAdmin = in_array($user->role, ['barangay_admin', 'system_admin'], true);
        abort_unless($isAdmin || (int) $report->user_id === (int) $user->id, 403);

        abort_unless($report->photo_path && Storage::disk('public')->exists($report->photo_path), 404, 'Report photo not found.');

        return Storage::disk('public')->response($report->photo_path, basename($report->photo_path), [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $reports = $request->user()->reports()->with('purok:id,name','street:id,purok_id,name')->latest()->paginate(20);
        return response()->json(['success'=>true,'data'=>$reports]);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $report->load('purok','street','satisfactions');
        $guide = $report->category ? KnowledgeBaseGuide::where('category', $report->category->value)->first(['id','category','guide_text']) : null;
        return response()->json(['success'=>true,'data'=>array_merge($report->toArray(), ['guided_resolution' => $guide])]);
    }

    public function emergency(Request $request, Report $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id, 403);
        if ($report->status === ReportStatus::Resolved) return response()->json(['success'=>false,'message'=>'Resolved reports cannot be marked emergency.'],422);
        $data = $request->validate(['reason'=>['required','string','max:1000']]);
        if (! $report->emergency_override) {
            $report->update(['emergency_override'=>true,'emergency_reason'=>$data['reason'],'emergency_triggered_at'=>now(),'priority'=>ReportPriority::Emergency]);
            app(ReportNotificationService::class)->send($request->user(), 'Emergency report submitted', 'Your report #'.$report->id.' was marked for urgent review.', $report, 'emergency');
            SendPushNotification::dispatch($request->user(), 'Emergency report submitted', 'Your report #'.$report->id.' was marked for urgent review.');
        }
        return response()->json(['success'=>true,'data'=>$report->fresh()->load('purok','street')]);
    }

    public function satisfaction(Request $request, Report $report): JsonResponse
    {
        abort_unless($report->user_id === $request->user()->id, 403);
        if ($report->status !== ReportStatus::Resolved) return response()->json(['success'=>false,'message'=>'Only resolved reports can be rated.'],422);
        $data = $request->validate(['rating'=>['required','integer','between:1,5'],'comment'=>['nullable','string','max:1000']]);
        if (Satisfaction::where('report_id', $report->id)->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['success'=>false,'message'=>'You have already rated this report.'],409);
        }
        $rating = Satisfaction::create(['report_id'=>$report->id,'user_id'=>$request->user()->id,...$data]);
        return response()->json(['success'=>true,'data'=>$rating],201);
    }
}
