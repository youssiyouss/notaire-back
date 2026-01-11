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
use App\Http\Controllers\dashboard\EducationalDocController;
use App\Http\Controllers\dashboard\EducationalVideoController;
use App\Http\Controllers\dashboard\ChatController;
use App\Http\Controllers\dashboard\AttendanceController;
use App\Http\Controllers\dashboard\DashboardController;
use App\Http\Controllers\clientSide\ContactController;
use App\Http\Controllers\clientSide\ClientDocumentController as ClientSideDocumentController;
use App\Http\Controllers\clientSide\ClientContractController as ClientSideContractController;
use App\Http\Controllers\dashboard\ContractProgressController;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:api']]);

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

// Public contact form route
Route::post('/contact', [ContactController::class, 'sendContactForm']);

Route::get('/user', [AuthController::class, 'user']);
Route::get('/verify-email/{token}', [ AuthController::class, 'verifyEmail']);
Route::post('/verification/resend/', [AuthController::class, 'resendVerificationEmail']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::middleware('auth:api')->get('/auth_user', function() {
    return response()->json(['user' => Auth::user()->load('client')]);
});
Route::middleware('auth:api')->put('/user/profile', [UserController::class, 'updateProfile']);
Route::middleware('auth:api')->post('/user/profile/picture', [UserController::class, 'updateProfilePicture']);

Route::middleware('auth:api')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::resource('users', UserController::class);
    Route::get('/users/{id}/suivi',  [UserController::class, 'suivi']);
    Route::resource('clients', ClientController::class);
    Route::post('clients/{id}/stats',  [ClientController::class, 'clientStats']);
    Route::resource('user-documents',ClientDocumentController::class);

    //Route::post('/process_image', [ClientController::class, 'process_image']);
    Route::post('/extract_text', [ClientController::class, 'extractText']);
    Route::resource('contracts_type', ContractTypeController::class);
    Route::post('contract-types/{contractTypeId}/rename', [ContractTypeController::class, 'rename']);
    Route::get('/contract-types', [ContractController::class, 'getContractTypes']);

    Route::resource('contracts', ContractController::class);
    Route::get('/contracts/{contract}/summarize', [ContractController::class,  'summarize']);

    // Contract Progress Management
    Route::prefix('contracts/{contractId}/progress')->group(function () {
        Route::get('/', [ContractProgressController::class, 'index']); // Get progress steps
        Route::post('/initialize', [ContractProgressController::class, 'initializeSteps']); // Initialize default steps
        Route::put('/steps/{stepId}', [ContractProgressController::class, 'updateStep']); // Update specific step
        Route::put('/', [ContractProgressController::class, 'updateSteps']); // Bulk update all steps
        Route::post('/advance', [ContractProgressController::class, 'advanceToNextStep']); // Auto-advance to next step
    });

    // Contract Signature Date
    Route::post('/contracts/{contractId}/signature-date', [ContractController::class, 'setSignatureDate']);

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
    Route::post('contract_templates/{id}/duplicate', [ContractTemplateController::class, 'duplicate']);
    Route::post('contract_templates/upload_summary', [ContractTemplateController::class, 'uploadSummary']);
    Route::delete('/contract_summary/{id}', [ContractTemplateController::class, 'deleteSummary']);
    Route::get('/users/{user}/client-details', [ContractController::class, 'getClientDetails']);
    // routes/api.php
    Route::get('/word-transformations/search', [ContractTemplateController::class, 'searchPlaceholders']);

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

    //educational pdfs
    Route::resource('doc-educationel', EducationalDocController::class);
    Route::get('doc-educationel/trash', [EducationalDocController::class, 'trash']);
    Route::patch('doc-educationel/{id}/restore', [EducationalDocController::class, 'restore']);
    Route::delete('doc-educationel/{id}/force', [EducationalDocController::class, 'forceDelete']);

    //educational videos
    Route::resource('video-educationel', EducationalVideoController::class);
    Route::get('video-educationel/trash', [EducationalVideoController::class, 'trash']);
    Route::patch('video-educationel/{id}/restore', [EducationalVideoController::class, 'restore']);
    Route::delete('video-educationel/{id}/force', [EducationalVideoController::class, 'forceDelete']);

    //Chat
    Route::get('/chat/{userId}', [ChatController::class, 'index']);
    Route::get('/chat/{userId}/users', [ChatController::class, 'getUsersList']);
    Route::delete('/chat/{msgID}/retirer', [ChatController::class, 'deleteForEveryone']);
    Route::delete('/chat/{msgID}/supprimer', [ChatController::class, 'deleteForMe']);
    Route::delete('/chat/{id}', [ChatController::class, 'destroy']);
    Route::post('/chat', [ChatController::class, 'store']);
    Route::post('/chat/upload', [ChatController::class, 'upload']);
    Route::post('/chat/forward', [ChatController::class, 'forward']);
    Route::get('chat/unread-count', [ChatController::class, 'unreadCount']);
    Route::get('chat/mark-as-read/{userId}', [ChatController::class, 'markAsRead']);
    Route::get('/chat/download/{id}', [ChatController::class, 's'])->name('chat.download');

    //Attendance
    Route::resource('attendances', AttendanceController::class);
    Route::get('/attendances/{id}/get',  [AttendanceController::class, 'getAttendances']);

});

// Client Side Routes
Route::middleware('auth:api')->prefix('client')->group(function () {
    Route::get('/documents', [ClientSideDocumentController::class, 'index']);
    Route::post('/documents', [ClientSideDocumentController::class, 'store']);
    Route::delete('/documents/{id}', [ClientSideDocumentController::class, 'destroy']);
    Route::get('/documents/{id}/download', [ClientSideDocumentController::class, 'download']);
    
    Route::get('/contracts', [ClientSideContractController::class, 'index']);
    Route::get('/contracts/{id}', [ClientSideContractController::class, 'show']);
});