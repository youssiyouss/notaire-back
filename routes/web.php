<?php

use Illuminate\Support\Facades\Route;
use App\Mail\DateSignatureAssigned;
use App\Models\Contract;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Test email preview
/*Route::get('/test-email', function () {
    // Get a contract with signature date for testing
    $contract = Contract::with(['notaire', 'clients.client'])->whereNotNull('signature_date')->first();
    
    if (!$contract) {
        return 'No contract with signature date found. Please assign a signature date first.';
    }
    
    $clientName = 'Test Client';
    $signatureDate = \Carbon\Carbon::parse($contract->signature_date)->format('d/m/Y à H:i');
    
    return new DateSignatureAssigned($contract, $clientName, $signatureDate);
});
*/
require __DIR__.'/auth.php';
