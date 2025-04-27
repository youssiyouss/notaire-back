<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractAttributes;
use App\Models\Client;
use App\Models\User;
use App\Models\ContractClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\File;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $contracts = Contract::with([
                'clients:id,nom,prenom,email',
                'creator:id,nom,prenom',
                'editor:id,nom,prenom',
                'template:id,contract_subtype,taxe_type,taxe_pourcentage,contract_type_id',
                'template.contractType:id,name' // Si tu veux afficher aussi le nom du type de contrat
            ])->paginate(20);

            return response()->json([
                'contracts' => $contracts
            ], 200);

            return response()->json([
                'contracts' => $contracts,
           ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function search_users(Request $request)
    {
        try {

            $query = $request->input('search'); // Correct way to retrieve POST data

            $users = User::where('nom', 'LIKE', "%{$query}%")
                ->orWhere('prenom', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orWhere('date_de_naissance', 'LIKE', "%{$query}%")
                ->limit(10)
                ->get();
            return response()->json($users);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }

    }

    public function getClientDetails($userId)
    {
        try {
            $user = User::with('client','documents')->findOrFail($userId);

            // Merge client data directly into user object
            $userData = $user->toArray();
            if ($user->client) {
                $userData['client'] = $user->client->toArray();
            }

            return response()->json($userData);

        } catch (\Exception $e) {
            Log::error('Error fetching client details: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $template = ContractTemplate::find($request->contractType);
        $clients = User::whereIn('id', array_column($request->clients, 'id'))->get();
        $buyers = User::whereIn('id', array_column($request->buyers, 'id'))->get();

        // Create contract record
        $contract = new Contract();
        $contract->template_id = $template->id;
        $contract->notaire_id = $request->notaryOffice;
        $contract->status = 'Non Payé';
        $contract->created_by = Auth::id();
        $contract->save();

        // Store attributes
        foreach ($request['attributes'] ?? [] as $attributeData) {
            ContractAttributes::create([
                'contract_id' => $contract->id,
                'name' => $attributeData['name'],
                'value' => $attributeData['value'],
            ]);
        }

        // Link clients & buyers
        foreach ($clients as $client) {
            ContractClient::create(['contract_id' => $contract->id, 'client_id' => $client->id]);
        }
        foreach ($buyers as $buyer) {
            ContractClient::create(['contract_id' => $contract->id, 'client_id' => $buyer->id]);
        }

        // Process the Word template
        $templatePath = storage_path("app/public/{$template->content}");
        $uniqueId = uniqid();
        $docxPath = storage_path("app/tmp_contract_{$uniqueId}.docx");
        $pdfOutputDir = storage_path("app/public/contracts");

        // Ensure contracts directory exists
        Storage::disk('public')->makeDirectory('contracts');

        $pdfFileName = "contract_{$contract->id}_" . time() . ".pdf";
        $pdfPath = "{$pdfOutputDir}/{$pdfFileName}";

        // Initialize template processor
        $processor = new TemplateProcessor($templatePath);

        // Replace placeholders in the correct order
        $processor = $this->replacePlaceholdersInTemplate($processor, $template, $clients, $request['attributes'], $buyers, $request->notaryOffice);
        // Save the modified template
        $processor->saveAs($docxPath);

        // Convert to PDF
        $libreOfficeBin = env('LIBREOFFICE_BIN');
        $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir " .
                escapeshellarg($pdfOutputDir) . ' ' . escapeshellarg($docxPath);
        shell_exec($command . " 2>&1");

        // Handle the generated PDF
        $generatedPdfPath = "{$pdfOutputDir}/" . pathinfo($docxPath, PATHINFO_FILENAME) . ".pdf";
        if (File::exists($generatedPdfPath)) {
            File::move($generatedPdfPath, $pdfPath);
        }

        // Update contract with PDF path
        $contract->pdf_path = "contracts/{$pdfFileName}";
        $contract->save();

        // Cleanup
        File::delete($docxPath);

        return response()->json([
            'message' => 'Contrat créé avec succès!',
            'contract' => $contract,
            'pdfUrl' => Storage::disk('public')->url("contracts/{$pdfFileName}")
        ], 201);
    }

    protected function replacePlaceholdersInTemplate($processor, $template, $clients, $attributes, $buyers, $notaryOfficeId)
    {
        // 1. Replace notary office placeholders
        $notary = User::find($notaryOfficeId);
        if ($notary) {
            $notaryName = $notary->nom . ' ' . $notary->prenom;
            $this->setValueWithEncoding($processor, 'Notaire', $notaryName);
        }

        // 2. Replace attributes (variables)
        foreach ($attributes as $attr) {
            $this->setValueWithEncoding($processor, $attr['name'] , $attr['value']);
        }

        // 3. Replace gender-specific pronouns
        $this->replaceArabicPronouns($processor, $template, $clients, $buyers);

        // Return the modified processor
        return $processor;
    }

    protected function replaceArabicPronouns($processor, $template, $clients, $buyers)
    {
        // Part A - clients (single angle brackets)
        $partA = json_decode($template->part_a_transformations, true);
        foreach ($partA as $trans) {
            $value = $this->determinePronounForm($trans, $clients);
            $this->setValueWithEncoding($processor, "A_{$trans['placeholder']}", $value);
        }

        // Part B - buyers (double angle brackets)
        $partB = json_decode($template->part_b_transformations, true);
        foreach ($partB as $trans) {
            $value = $this->determinePronounForm($trans, $buyers);
            $this->setValueWithEncoding($processor, "B_{$trans['placeholder']}", $value);
        }

        // All parties (triple angle brackets)
        $partAll =  json_decode($template->part_all_transformations, true);
        foreach ($partAll as $trans) {
            $value = $this->determinePronounFormAll($trans, $clients, $buyers);
            $this->setValueWithEncoding($processor,"All_{$trans['placeholder']}", $value);
        }
    }

    protected function setValueWithEncoding($processor, $search, $replace)
    {
        try {
            $processor->setValue($search, $replace);
        } catch (\Exception $e) {
            \Log::error("Replacement failed for placeholder: $search", ['error' => $e->getMessage()]);
        }
    }



    protected function replacePlaceholder($processor, $search, $replace)
    {
        // Use reflection to access the protected 'tempDocument' property
        $reflection = new \ReflectionClass($processor);
        $property = $reflection->getProperty('tempDocument');
        $property->setAccessible(true);
        $document = $property->getValue($processor);

        foreach ($document as $key => $part) {
            $document[$key] = str_replace($search, $replace, $part);
        }

        $property->setValue($processor, $document);
    }

    protected function setValueWithRetry($processor, $search, $replace)
    {
        try {
            // First try the normal way
            $processor->setValue($search, $replace);
        } catch (\Exception $e) {
            // If that fails, try with HTML entities for Arabic characters
            $searchEncoded = mb_convert_encoding($search, 'HTML-ENTITIES', 'UTF-8');
            $replaceEncoded = mb_convert_encoding($replace, 'HTML-ENTITIES', 'UTF-8');
            $processor->setValue($searchEncoded, $replaceEncoded);
        }
    }

    protected function determinePronounForm($transformation, $users)
    {
        $maleCount = 0;
        $femaleCount = 0;

        foreach ($users as $user) {
            // Make case-insensitive comparison
            if (strtolower($user->sexe) === 'male') {
                $maleCount++;
            } else {
                $femaleCount++;
            }
        }

        if ($maleCount > 0 && $femaleCount === 0) {
            return $maleCount > 1 ? $transformation['malepluralForm'] : $transformation['maleForm'];
        } elseif ($femaleCount > 0 && $maleCount === 0) {
            return $femaleCount > 1 ? $transformation['femalepluralForm'] : $transformation['femaleForm'];
        } else {
            return $transformation['malepluralForm'];
        }
    }

    protected function determinePronounFormAll($transformation, $clients, $buyers)
    {
        // Count genders among all parties
        $maleCount = 0;
        $femaleCount = 0;

        foreach ($clients as $client) {
            $client->sexe === 'male' ? $maleCount++ : $femaleCount++;
        }

        foreach ($buyers as $buyer) {
            $buyer->sexe === 'male' ? $maleCount++ : $femaleCount++;
        }

        // Determine which form to use based on Arabic grammar rules
        if ($maleCount > 0 && $femaleCount === 0) {
            // All male
            return $maleCount > 1 ? $transformation['malepluralForm'] : $transformation['maleForm'];
        } elseif ($femaleCount > 0 && $maleCount === 0) {
            // All female
            return $femaleCount > 1 ? $transformation['femalepluralForm'] : $transformation['femaleForm'];
        } else {
            // Mixed group - in Arabic, masculine plural is used for mixed groups
            return $transformation['malepluralForm'];
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $contract = Contract::with('template','notaire','clients')->findOrFail($id);
            return response()->json(['contract' => $contract], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'User not found.'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
     public function destroy(string $id)
    {
        try {
            $Contract = Contract::FindOrFail($id);
            $Contract->delete();

            return response()->json(['message' => 'Employee deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

}
