<?php

use App\Http\Controllers\VideoCallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'secure.auth'])->prefix('api/video-calls')->name('video-calls.')->group(function () {
    Route::post('/', [VideoCallController::class, 'create'])->name('create');
    Route::get('/incoming', [VideoCallController::class, 'incoming'])->name('incoming');

    Route::get('/{callId}', [VideoCallController::class, 'show'])->whereNumber('callId')->name('show');
    Route::post('/{callId}/accept', [VideoCallController::class, 'accept'])->whereNumber('callId')->name('accept');
    Route::post('/{callId}/decline', [VideoCallController::class, 'decline'])->whereNumber('callId')->name('decline');
    Route::post('/{callId}/end', [VideoCallController::class, 'end'])->whereNumber('callId')->name('end');

    Route::post('/{callId}/signals', [VideoCallController::class, 'signal'])->whereNumber('callId')->name('signal');
    Route::get('/{callId}/signals', [VideoCallController::class, 'signals'])->whereNumber('callId')->name('signals');
});
