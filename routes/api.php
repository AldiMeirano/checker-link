<?php

use App\Http\Controllers\CrapeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GifterCheckerController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/gifter-checker/{path}', [GifterCheckerController::class, 'gifterCheckerNawala'])
    ->where('path', '(gifters|reg|victory)');
Route::post('/hai', [CrapeController::class, 'sayHai']);
