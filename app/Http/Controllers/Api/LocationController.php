<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Purok;
use Illuminate\Http\JsonResponse;
class LocationController extends Controller
{
    public function puroks(): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>Purok::with('barangay:id,name')->orderBy('name')->get(['id','barangay_id','name'])]);
    }
    public function streets(Purok $purok): JsonResponse
    {
        return response()->json(['success'=>true,'data'=>$purok->streets()->orderBy('name')->get(['id','purok_id','name'])]);
    }
}
