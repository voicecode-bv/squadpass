<?php

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StripCardController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketMessageController;
use App\Http\Controllers\Api\V1\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('clubs', [ClubController::class, 'index']);
        Route::get('schedule', [ScheduleController::class, 'index']);

        Route::get('enrollments', [EnrollmentController::class, 'index']);
        Route::post('enrollments/single', [EnrollmentController::class, 'storeSingle']);
        Route::post('enrollments/fixed', [EnrollmentController::class, 'storeFixed']);
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->whereNumber('enrollment');
        Route::post('absences', [AbsenceController::class, 'store']);

        Route::get('strip-cards', [StripCardController::class, 'index']);
        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::get('visits', [VisitController::class, 'index']);

        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
        Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->whereNumber('ticket');
    });
});
