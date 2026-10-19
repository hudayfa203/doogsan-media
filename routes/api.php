<?php

use App\Http\Controllers\Api\PulseController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only JSON used by the site's live layer. When WebSockets
| are unavailable the browser polls /api/{locale}/pulse instead.
*/
Route::middleware('throttle:api')->group(function () {
    Route::get('{locale}/pulse', [PulseController::class, 'pulse'])->whereIn('locale', ['en', 'so'])->name('api.pulse');
    Route::get('{locale}/latest', [PulseController::class, 'latest'])->whereIn('locale', ['en', 'so'])->name('api.latest');
    Route::get('live/{event}/updates', [PulseController::class, 'liveUpdates'])->whereNumber('event')->name('api.live.updates');
    Route::get('tv/status', [PulseController::class, 'tvStatus'])->name('api.tv.status');
});

Route::post('beacon', [PulseController::class, 'beacon'])->middleware('throttle:beacon')->name('api.beacon');
Route::post('ads/{ad}/impression', [PulseController::class, 'adImpression'])->whereNumber('ad')->middleware('throttle:beacon')->name('api.ad.impression');
