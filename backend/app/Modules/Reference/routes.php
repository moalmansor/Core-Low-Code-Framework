<?php

declare(strict_types=1);

use App\Modules\Reference\Http\Controllers\ReferenceDataController as R;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/business-calendars', [R::class, 'calendars']);
    Route::post('/business-calendars', [R::class, 'storeCalendar']);
    Route::patch('/business-calendars/{calendar}', [R::class, 'updateCalendar']);
    Route::delete('/business-calendars/{calendar}', [R::class, 'destroyCalendar']);
    Route::get('/business-calendars/{calendar}/holidays', [R::class, 'holidays']);
    Route::post('/business-calendars/{calendar}/holidays', [R::class, 'storeHoliday']);
    Route::patch('/business-calendars/{calendar}/holidays/{holiday}', [R::class, 'updateHoliday']);
    Route::delete('/business-calendars/{calendar}/holidays/{holiday}', [R::class, 'destroyHoliday']);

    Route::get('/number-sequences', [R::class, 'sequences']);
    Route::post('/number-sequences', [R::class, 'storeSequence']);
    Route::patch('/number-sequences/{sequence}', [R::class, 'updateSequence']);
    Route::post('/number-sequences/{sequence}/adjust', [R::class, 'adjustSequence']);

    Route::get('/currencies', [R::class, 'currencies']);
    Route::post('/currencies', [R::class, 'storeCurrency']);
    Route::patch('/currencies/{currency}', [R::class, 'updateCurrency']);
    Route::get('/exchange-rates', [R::class, 'rates']);
    Route::post('/exchange-rates', [R::class, 'storeRate']);

    Route::get('/units', [R::class, 'units']);
    Route::post('/units', [R::class, 'storeUnit']);
    Route::patch('/units/{unit}', [R::class, 'updateUnit']);
    Route::delete('/units/{unit}', [R::class, 'destroyUnit']);
});
