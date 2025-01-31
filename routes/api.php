<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\dashboard\AuthController;
use App\Http\Controllers\dashboard\UserController;
use App\Http\Controllers\dashboard\ClientController;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*Mail::raw('Testing email', function ($message) {
    $message->to('yousseramcf@gmail.com')->subject('Test Email');
});*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('sendResetPasswordLink', [AuthController::class, 'sendResetPasswordLink']);
Route::post('resetPassword', [AuthController::class, 'resetPassword']);

Route::get('/verify-email/{token}', [ AuthController::class, 'verifyEmail']);
Route::post('/verification/resend/', [AuthController::class, 'resendVerificationEmail']);


Route::middleware('auth:api')->group(function () {
    Route::resource('clients', ClientController::class);
    Route::resource('users', UserController::class);
});

