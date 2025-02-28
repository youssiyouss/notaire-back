<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\dashboard\AuthController;
use App\Http\Controllers\dashboard\UserController;
use App\Http\Controllers\dashboard\ClientController;

/*Mail::raw('Testing email', function ($message) {
    $message->to('yousseramcf@gmail.com')->subject('Test Email');
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('sendResetPasswordLink', [AuthController::class, 'sendResetPasswordLink']);
Route::post('resetPassword', [AuthController::class, 'resetPassword']);

Route::get('/user', [AuthController::class, 'user']);
Route::get('/verify-email/{token}', [ AuthController::class, 'verifyEmail']);
Route::post('/verification/resend/', [AuthController::class, 'resendVerificationEmail']);
Route::post('/change-password', [AuthController::class, 'changePassword']);


Route::middleware('auth:api')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('clients', ClientController::class);
    Route::post('/process_image', [ClientController::class, 'process_image']);

});

