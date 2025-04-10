<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\dashboard\AuthController;
use App\Http\Controllers\dashboard\UserController;
use App\Http\Controllers\dashboard\ClientController;
use App\Http\Controllers\dashboard\ContractTypeController;
use App\Http\Controllers\dashboard\ContractController;
use App\Http\Controllers\dashboard\ContractAttributesController;
use App\Http\Controllers\dashboard\ContractTemplateController;

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
    //Route::post('/process_image', [ClientController::class, 'process_image']);
    Route::post('/extract_text', [ClientController::class, 'extractText']);
    Route::resource('contracts_type', ContractTypeController::class);
    Route::post('contract-types/{contractTypeId}/rename', [ContractTypeController::class, 'rename']);
    Route::get('/contract-types', [ContractController::class, 'getContractTypes']);

    Route::resource('contracts', ContractController::class);

    Route::prefix('contract-attributes')->group(function () {
        Route::post('/', [ContractAttributesController::class, 'store']); // Add attributes
        Route::get('/{contract_subtype_id}', [ContractAttributesController::class, 'index']); // List attributes
        Route::delete('/{id}', [ContractAttributesController::class, 'delete']); // Delete an attribute
        Route::put('/{id}', [ContractAttributesController::class, 'rename']); // Rename an attribute
    });

    Route::get('/notaires', [UserController::class, 'getNotaryOffices']);
    Route::get('/clients', [ContractController::class, 'searchClients']);
    Route::get('/buyers', [ContractController::class, 'searchBuyers']);
    Route::get('/get_users', [ContractController::class, 'search_users']);

    Route::resource('contract_templates', ContractTemplateController::class);
    Route::get('contract_templates/{id}/attributes', [ContractTemplateController::class, 'getAttributes']);
    Route::get('/users/{user}/client-details', [ContractController::class, 'getClientDetails']);

    Route::get('contracts/{contract}', function ($contractId) {
    $contract = Contract::findOrFail($contractId);
    $path = storage_path('app/public/contracts/' . $contractId . '.pdf');

    return response()->file($path);
});


});

