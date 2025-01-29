<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/process', [PaymentController::class, 'process']);
Route::post('/api/webhook/superwalletz/{transaction_id}', [PaymentController::class, 'superWalletzWebhook'])->name('webhook.superwalletz');
