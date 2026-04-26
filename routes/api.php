<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataStreamController;
use App\Http\Controllers\MqttHandler;
use App\Http\Controllers\SecretKey;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VirtualPinController;
use App\Models\VirtualPin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('users')->group(function () {
        Route::post('/', [UserController::class, 'create']);
        Route::put('/{id}', [UserController::class, 'update']);
    });

    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('jwt')->get('/profile', [AuthController::class, 'profile']);
    Route::middleware('jwt')->post('/logout', [AuthController::class, 'logout']);

    Route::prefix('secret-keys')->middleware('jwt')->group(function () {
        Route::get('/', [SecretKey::class, 'index']);
        Route::post('/generate', [SecretKey::class, 'generate']);
        Route::delete('/{id}', [SecretKey::class, 'destroy']);
    });

    Route::prefix('virtual-pins')->middleware('jwt')->group(function () {
        Route::post('/create', [VirtualPinController::class, 'create']);
        Route::get('/{device_id}', [VirtualPinController::class, 'index']);
        Route::get('/find/{id}', [VirtualPinController::class, 'find']);
    });

    Route::prefix('data-streams')->middleware('jwt')->group(function () {
        Route::get('/', [DataStreamController::class, 'index']);
        Route::post('/', [DataStreamController::class, 'create']);
    });

    Route::get('/mutate-state', [DataStreamController::class, 'mutateState']);
    Route::get('/get-device-state', [DataStreamController::class, 'getDeviceStates']);

    Route::prefix('mqtt')->middleware('jwt')->group(function () {
        Route::post('/publish', [MqttHandler::class, 'publish']);
    });
});
