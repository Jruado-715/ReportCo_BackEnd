<?php
namespace App\Http\Controllers\Admin;
use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\Report;
use App\Models\ReportEscalation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\ReportNotificationService;
class ReportAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q=Report::with('user:id,name,email','purok:id,name','street:id,purok_id,name')->latest();
        foreach(['purok_id','street_id','category','priority','status'] as $field) if($request->filled($field)) $q->where($field,$request->input($field));
        if($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $q->where(function ($query) use ($term) {
                $query->whereRaw('LOWER(description) LIKE ?', ['%'.strtolower($term).'%']);
                if (ctype_digit($term)) $query->orWhere('id', (int) $term);
                $query->orWhereHas('street', fn ($street) => $street->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($term).'%']));
                $query->orWhereHas('purok', fn ($purok) => $purok->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($term).'%']));
            });
        }
        if($request->filled('from')) $q->whereDate('created_at','>=',$request->input('from'));
        if($request->filled('to')) $q->whereDate('created_at','<=',$request->input('to'));
        return response()->json(['success'=>true,'data'=>$q->paginate(25)]);
    }
    public function show(Report $report): JsonResponse
    {
        $report->load([
            'user:id,name,email',
            'purok:id,name,barangay_id',
            'purok.barangay:id,name',
            'street:id,purok_id,name',
            'escalations' => fn ($q) => $q->latest('escalated_at'),
            'satisfactions',
        ]);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function status(Request $request, Report $report): JsonResponse
    {
        $data=$request->validate(['status'=>['required','in:received,in_progress,escalated,resolved']]);
        $new=ReportStatus::from($data['status']); $old=$report->status;
        $allowed=[ReportStatus::Received->value=>[ReportStatus::InProgress->value,ReportStatus::Escalated->value,ReportStatus::Resolved->value],ReportStatus::InProgress->value=>[ReportStatus::Escalated->value,ReportStatus::Resolved->value],ReportStatus::Escalated->value=>[ReportStatus::Resolved->value],ReportStatus::Resolved->value=>[]];
        if(!in_array($new->value,$allowed[$old->value]??[],true) && $new!==$old) return response()->json(['success'=>false,'message'=>"Invalid status transition {$old->value} -> {$new->value}."],422);
        $report->status=$new; $report->resolved_at=$new===ReportStatus::Resolved?now():null; $report->save();
        $messages=['in_progress'=>'Your report is now being worked on.','escalated'=>'Your report has been escalated for higher-level action.','resolved'=>'Your report has been marked resolved.','received'=>'Your report was returned to received status.'];
        app(ReportNotificationService::class)->send($report->user, $new->name, $messages[$new->value], $report, 'status_update');
        SendPushNotification::dispatch($report->user,$new->name,$messages[$new->value]);
        return response()->json(['success'=>true,'data'=>$report->fresh()->load('purok','street')]);
    }
    public function emergency(Request $request, Report $report): JsonResponse
    {
        if ($report->status === ReportStatus::Resolved) {
            return response()->json(['success'=>false,'message'=>'Resolved reports cannot be marked emergency.'],422);
        }
        $data=$request->validate(['reason'=>['required','string','max:1000']]);
        if (! $report->emergency_override) {
            $report->update(['emergency_override'=>true,'emergency_reason'=>$data['reason'],'emergency_triggered_at'=>now(),'priority'=>ReportPriority::Emergency]);
            app(ReportNotificationService::class)->send($report->user,'Emergency report update','Your report #'.$report->id.' has been escalated for urgent review.', $report, 'emergency');
            SendPushNotification::dispatch($report->user,'Emergency report update','Your report #'.$report->id.' has been escalated for urgent review.');
        }
        return response()->json(['success'=>true,'data'=>$report->fresh()]);
    }
    public function escalate(Request $request, Report $report): JsonResponse
    {
        if($report->status===ReportStatus::Resolved) return response()->json(['success'=>false,'message'=>'Resolved reports cannot be escalated.'],422);
        $data=$request->validate(['receiving_office'=>['required','string','max:255'],'reason'=>['required','string','max:2000'],'notes'=>['nullable','string','max:2000']]);
        $existing=$report->escalations()->whereIn('status',['pending','submitted','acknowledged'])->first();
        if($existing) return response()->json(['success'=>false,'message'=>'This report already has an active escalation.'],409);
        $esc=DB::transaction(function()use($data,$report,$request){$e=ReportEscalation::create([...$data,'report_id'=>$report->id,'escalated_by'=>$request->user()->id,'escalated_at'=>now(),'status'=>'pending']);$report->update(['status'=>ReportStatus::Escalated]);return $e;});
        $url=config('services.lgu.endpoint');
        if($url){
            try{$r=Http::timeout(10)->post($url,['report_id'=>$report->id,'office'=>$esc->receiving_office,'reason'=>$esc->reason,'notes'=>$esc->notes]); if($r->successful())$esc->update(['status'=>'submitted','external_reference'=>$r->json('reference')]);}catch(\Throwable $e){logger()->warning('LGU endpoint unavailable',['error'=>$e->getMessage()]);}
        }
        app(ReportNotificationService::class)->send($report->user,'Report escalated','Your report has been escalated to '.$esc->receiving_office.'.', $report, 'escalation');
        SendPushNotification::dispatch($report->user,'Report escalated','Your report has been escalated to '.$esc->receiving_office.'.');
        return response()->json(['success'=>true,'data'=>$esc->fresh()],201);
    }
}
