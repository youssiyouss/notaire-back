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
use App\Http\Controllers\dashboard\ClientDocumentController;
use App\Http\Controllers\dashboard\TaxController;
use App\Http\Controllers\dashboard\CompanyController;
use App\Http\Controllers\dashboard\TaskController;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:api']]);

/*Mail::raw('Testing email', function ($message) {
    $message->to('yousseramcf@gmail.com')->subject('Test Email');
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/

Route::get('/test-cors', function() {
    return response()->json(['message' => 'CORS working!']);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('sendResetPasswordLink', [AuthController::class, 'sendResetPasswordLink']);
Route::post('resetPassword', [AuthController::class, 'resetPassword']);

Route::get('/user', [AuthController::class, 'user']);
Route::get('/verify-email/{token}', [ AuthController::class, 'verifyEmail']);
Route::post('/verification/resend/', [AuthController::class, 'resendVerificationEmail']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::middleware('auth:api')->get('/auth_user', function() {
    return response()->json(['user' => Auth::user()]);
});

Route::middleware('auth:api')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('clients', ClientController::class);
    Route::resource('user-documents',ClientDocumentController::class);

    //Route::post('/process_image', [ClientController::class, 'process_image']);
    Route::post('/extract_text', [ClientController::class, 'extractText']);
    Route::resource('contracts_type', ContractTypeController::class);
    Route::post('contract-types/{contractTypeId}/rename', [ContractTypeController::class, 'rename']);
    Route::get('/contract-types', [ContractController::class, 'getContractTypes']);

    Route::resource('contracts', ContractController::class);
    Route::get('/contracts/{contract}/summarize', [ContractController::class,  'summarize']);

    Route::prefix('contract-attributes')->group(function () {
        Route::post('/', [ContractAttributesController::class, 'store']); // Add attributes
        Route::get('/{contract_subtype_id}', [ContractAttributesController::class, 'index']); // List attributes
        Route::delete('/{id}', [ContractAttributesController::class, 'delete']); // Delete an attribute
        Route::put('/{id}', [ContractAttributesController::class, 'rename']); // Rename an attribute
    });

    Route::get('/notaires', [UserController::class, 'getNotaryOffices']);
    Route::get('/buyers', [ContractController::class, 'searchBuyers']);
    Route::get('/get_users', [ContractController::class, 'search_users']);

    Route::resource('contract_templates', ContractTemplateController::class);
    Route::get('contract_templates/{id}/attributes', [ContractTemplateController::class, 'getGroups']);
    Route::post('contract_templates/upload_summary', [ContractTemplateController::class, 'uploadSummary']);
    Route::delete('/contract_summary/{id}', [ContractTemplateController::class, 'deleteSummary']);
    Route::get('/users/{user}/client-details', [ContractController::class, 'getClientDetails']);

    //Liste des formulaires fiscaux
    Route::resource('impots', TaxController::class);
    Route::post('/generateBon', [TaxController::class, 'generateBon']);
    Route::post('/bon/final', [TaxController::class, 'generateFinalBon']);

    // In routes/web.php
    Route::get('/download-tax-report', function (Request $request) {
        // Same logic as store() but only returns DOCX
    })->name('download.tax.report');

    //Companies
    Route::get('/companies/search', [CompanyController::class, 'search']);
    Route::resource('companies', CompanyController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    //Kanban board
    Route::resource('tasks', TaskController::class);

    //Notifications
    Route::get('/notifications', function (Request $request) {
        return response()->json([
            'data' => auth()->user()->notifications()->latest()->take(20)->get()// ou ->unreadNotifications  return
        ]);
    });

    Route::post('/notifications/{id}/read', function ($id) {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['status' => 'read']);
    });


    Route::post('/notifications/mark-all-read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 'success']);
    });
});

