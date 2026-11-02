<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\Driver\TransferController as DriverTransferController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Transfer API v1
|--------------------------------------------------------------------------
| Herkese açık uçlar (web sitesi / mobil). Authorization: Bearer <acente token>
| gönderilirse istek acente adına yapılır (indirimli fiyat + acente kaydı).
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::get('zones', [CatalogController::class, 'zones'])->name('zones');
        Route::get('locations', [CatalogController::class, 'locations'])->name('locations');
        Route::get('vehicle-types', [CatalogController::class, 'vehicleTypes'])->name('vehicle-types');
        Route::get('extras', [CatalogController::class, 'extras'])->name('extras');
        Route::get('geo/zone', [CatalogController::class, 'resolveZone'])->name('geo.zone');

        Route::post('quotes', QuoteController::class)->name('quotes');
    });

    Route::middleware('throttle:bookings')->group(function () {
        Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservations/{code}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservations/{code}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    // B2B acente
    Route::middleware(['auth:sanctum', 'tokenable:agency'])->prefix('agency')->name('agency.')->group(function () {
        Route::get('reservations', [ReservationController::class, 'agencyIndex'])->name('reservations.index');
    });

    // Sürücü uygulaması
    Route::middleware(['auth:sanctum', 'tokenable:driver'])->prefix('driver')->name('driver.')->group(function () {
        Route::get('transfers', [DriverTransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{transfer}', [DriverTransferController::class, 'show'])->name('transfers.show');
        Route::patch('transfers/{transfer}/status', [DriverTransferController::class, 'updateStatus'])->name('transfers.status');
    });
});
