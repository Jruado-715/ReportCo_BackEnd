<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\IotReading;
use Illuminate\Http\JsonResponse;
class IotAdminController extends Controller
{
    public function readings(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>IotReading::latest('recorded_at')->paginate(30)]);
    }
    public function alerts(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>Announcement::where('type','emergency')->latest()->paginate(30)]);
    }
}
