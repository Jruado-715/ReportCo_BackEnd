<?php
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\IotAdminController;
use App\Http\Controllers\Admin\ReportAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\IotReadingController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function(){
    Route::middleware('throttle:5,1')->post('/register',[AuthController::class,'register']);
    Route::middleware('throttle:5,1')->post('/login',[AuthController::class,'login']);
    Route::middleware('api.token')->post('/logout',[AuthController::class,'logout']);
    Route::middleware('api.token')->get('/me',[AuthController::class,'me']);
});

Route::get('/puroks',[LocationController::class,'puroks']);
Route::middleware('api.token')->get('/map/reverse-geocode',[MapController::class,'reverseGeocode']);
Route::middleware('api.token')->get('/map/mankilam-boundary',[MapController::class,'mankilamBoundary']);
Route::get('/puroks/{purok}/streets',[LocationController::class,'streets']);
Route::middleware('device')->post('/iot/readings',[IotReadingController::class,'store']);

Route::middleware('api.token')->group(function(){
    Route::post('/reports',[ReportController::class,'store']);
    Route::get('/reports/mine',[ReportController::class,'mine']);
    Route::get('/reports/{report}/photo',[ReportController::class,'photo']);
    Route::get('/reports/{report}',[ReportController::class,'show']);
    Route::post('/reports/{report}/emergency',[ReportController::class,'emergency']);
    Route::post('/reports/{report}/satisfaction',[ReportController::class,'satisfaction']);
    Route::post('/device-tokens',[DeviceTokenController::class,'store']);
    Route::get('/notifications',[NotificationController::class,'index']);
    Route::patch('/notifications/{notification}/read',[NotificationController::class,'read']);
    Route::post('/notifications/read-all',[NotificationController::class,'readAll']);

    Route::prefix('admin')->middleware('role:barangay_admin,system_admin')->group(function(){
        Route::get('/reports',[ReportAdminController::class,'index']);
        Route::get('/reports/{report}',[ReportAdminController::class,'show']);
                Route::patch('/reports/{report}/status',[ReportAdminController::class,'status']);
        Route::post('/reports/{report}/emergency',[ReportAdminController::class,'emergency']);
        Route::post('/reports/{report}/escalate',[ReportAdminController::class,'escalate']);
        Route::get('/analytics/puroks',[AnalyticsController::class,'purokRanking']);
        Route::get('/analytics/streets',[AnalyticsController::class,'streetRanking']);
        Route::get('/analytics/summary',[AnalyticsController::class,'summary']);
        Route::get('/analytics/heatmap',[AnalyticsController::class,'heatmap']);
        Route::get('/analytics/categories',[AnalyticsController::class,'categoryBreakdown']);
        Route::get('/analytics/statuses',[AnalyticsController::class,'statusBreakdown']);
        Route::get('/map/reports',[MapController::class,'reports']);
        Route::get('/iot/readings',[IotAdminController::class,'readings']);
        Route::get('/alerts',[IotAdminController::class,'alerts']);
        Route::post('/announcements',[AnnouncementController::class,'store']);
    });
});
