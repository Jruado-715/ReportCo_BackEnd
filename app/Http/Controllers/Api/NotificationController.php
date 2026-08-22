<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ReportNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse { $items=$request->user()->reportNotifications()->with('report:id,description,status,priority')->latest()->paginate(30); return response()->json(['success'=>true,'data'=>$items,'unread_count'=>$request->user()->reportNotifications()->whereNull('read_at')->count()]); }
    public function read(Request $request, ReportNotification $notification): JsonResponse { abort_unless($notification->user_id === $request->user()->id,403); $notification->update(['read_at'=>now()]); return response()->json(['success'=>true,'data'=>$notification->fresh()]); }
    public function readAll(Request $request): JsonResponse { $request->user()->reportNotifications()->whereNull('read_at')->update(['read_at'=>now()]); return response()->json(['success'=>true]); }
}
