<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\OrganizationTreeController;
use Illuminate\Support\Facades\Route;

// Public Endpoint (e.g. used by Purchasing Service)
Route::get('/branches', [BranchController::class, 'index']);

// Protected routes (require valid access token from cookies)
Route::middleware('jwt.auth')->group(function () {
    // Branch Detail
    Route::get('/branches/{id}', [BranchController::class, 'show']);

    // Positions Read
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/positions/{id}', [PositionController::class, 'show']);

    // Employees Read & Org Tree
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::get('/employees/{id}/org-tree', [OrganizationTreeController::class, 'show']);
});

// Admin-only write operations
Route::middleware(['jwt.auth', 'jwt.superadmin'])->group(function () {
    // Branches Write
    Route::post('/branches', [BranchController::class, 'store']);
    Route::put('/branches/{id}', [BranchController::class, 'update']);
    Route::delete('/branches/{id}', [BranchController::class, 'destroy']);

    // Positions Write
    Route::post('/positions', [PositionController::class, 'store']);
    Route::put('/positions/{id}', [PositionController::class, 'update']);
    Route::delete('/positions/{id}', [PositionController::class, 'destroy']);

    // Employees Write
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});