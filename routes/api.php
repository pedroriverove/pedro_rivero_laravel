<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::post('/process', [PaymentController::class, 'process']);
Route::post('/webhook/superwalletz/{transaction_id}', [PaymentController::class, 'superWalletzWebhook'])->name('webhook.superwalletz');
