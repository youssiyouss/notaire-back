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
use ZipArchive;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $contracts = Contract::with([
                'clients.client',
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

            $users = User::where('role', 'Client')
            ->where(function($q) use ($query) {
                $q->where('nom', 'LIKE', "%{$query}%")
                  ->orWhere('prenom', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('date_de_naissance', 'LIKE', "%{$query}%");
            })
            ->limit(15)
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
        // 1️⃣ Load template & parties
        $template = ContractTemplate::find($request->contractType);
        $clients  = User::whereIn('id', array_column($request->clients, 'id'))->get();
        $buyers   = User::whereIn('id', array_column($request->buyers, 'id'))->get();

        // 2️⃣ Create the Contract record
        $contract = Contract::create([
            'template_id' => $template->id,
            'notaire_id'  => $request->notaryOffice,
            'status'      => 'Non Payé',
            'created_by'  => Auth::id(),
        ]);

        // 3️⃣ Persist each attribute
        foreach ($request->attributes ?? [] as $attr) {
            ContractAttributes::create([
                'contract_id' => $contract->id,
                'name'        => $attr['name'],
                'value'       => $attr['value'],
            ]);
        }

        // 4️⃣ Link clients & buyers
        foreach ($clients as $client) {
            ContractClient::create([
                'contract_id' => $contract->id,
                'client_id'   => $client->id,
                'etat'        => $request->clientType,
                'type'        => 'PartA',
            ]);
        }
        foreach ($buyers as $buyer) {
            ContractClient::create([
                'contract_id' => $contract->id,
                'client_id'   => $buyer->id,
                'etat'        => $request->buyerType,
                'type'        => 'PartB',
            ]);
        }

        // 5️⃣ Paths
        Storage::disk('public')->makeDirectory('contracts/pdf');
        Storage::disk('public')->makeDirectory('contracts/word');

        $templatePath = storage_path("app/public/{$template->content}");
        $uniqueId     = uniqid();
        $tempDocxPath = storage_path("app/tmp_contract_{$uniqueId}.docx"); // ✅ temp path
        $pdfFileName  = "contract_{$contract->id}_" . time() . ".pdf";
        $docxFileName = "contract_{$contract->id}_" . time() . ".docx";

        $pdfFinalPath  = storage_path("app/public/contracts/pdf/{$pdfFileName}");  // ✅ final PDF
        $docxFinalPath = storage_path("app/public/contracts/word/{$docxFileName}"); // ✅ final DOCX

        // 6️⃣ Replacements
        $replacements = [];
        foreach ($request->input('attributes', []) as $attr) {
            $replacements[$attr['name']] = $attr['value'];
        }

        $notary = User::find($request->notaryOffice);
        if ($notary) {
            $notaryFullName = $notary->nom . ' ' . $notary->prenom;
            $replacements['اسم_الموثق'] = $notaryFullName;
        }

        $pronounPlaceholders = json_decode($template->part_a_transformations, true);
        foreach ($pronounPlaceholders as $trans) {
            $value = $this->determinePronounForm($trans, $clients);
            $replacements[$trans['placeholder']] = $value;
        }

        $pronounPlaceholders = json_decode($template->part_b_transformations, true);
        foreach ($pronounPlaceholders as $trans) {
            $value = $this->determinePronounForm($trans, $buyers);
            $replacements[$trans['placeholder']] = $value;
        }

        $pronounPlaceholders = json_decode($template->part_all_transformations, true);
        foreach ($pronounPlaceholders as $trans) {
            $value = $this->determinePronounFormAll($trans, $clients, $buyers);
            $replacements[$trans['placeholder']] = $value;
        }

        // 7️⃣ Inject into temporary .docx
        $this->injectBookmarks($templatePath, $tempDocxPath, $replacements);

        // 🆕 Save permanent .docx
        File::copy($tempDocxPath, $docxFinalPath);
        $contract->update([
            'word_path'  => "contracts/word/{$docxFileName}"
        ]);
        // 8️⃣ Convert to PDF via LibreOffice
        $libreOfficeBin = env('LIBREOFFICE_BIN');
        $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir "
                . escapeshellarg(storage_path("app/public/contracts/pdf")) . ' '
                . escapeshellarg($tempDocxPath)
                . " 2>&1";
        $output = shell_exec($command);
        \Log::info("LibreOffice Output: " . $output);

        // 9️⃣ Verify PDF exists and update DB
        $generatedPdfPath = storage_path("app/public/contracts/pdf/") . pathinfo($tempDocxPath, PATHINFO_FILENAME) . ".pdf";
        if (File::exists($generatedPdfPath)) {
            File::move($generatedPdfPath, $pdfFinalPath);
            $contract->update([
                'pdf_path'  => "contracts/pdf/{$pdfFileName}"
            ]);
            \Log::info("PDF moved successfully to: " . $pdfFinalPath);
        } else {
            \Log::error("PDF not found at expected path: " . $generatedPdfPath);
            return response()->json([
                'message' => 'Erreur : PDF introuvable après la génération.',
                'commandOutput' => $output,
                'pathTried' => $generatedPdfPath,
            ], 500);
        }

        // 🔟 Cleanup temp file
        File::delete($tempDocxPath);

        // 🔁 Return response
        return response()->json([
            'message'   => 'Contrat créé avec succès!',
            'contract'  => $contract
        ], 201);
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

    protected function injectBookmarks($sourceDocx, $targetDocx, array $replacements)
    {
        copy($sourceDocx, $targetDocx);
        $zip = new \ZipArchive();
        if ($zip->open($targetDocx) !== true) {
            throw new \Exception("Cannot open DOCX");
        }

        $xml = $zip->getFromName('word/document.xml');
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($replacements as $name => $value) {
            $starts = $xpath->query("//w:bookmarkStart[@w:name='$name']");
            foreach ($starts as $bmStart) {
                $bmId = $bmStart->getAttribute('w:id');

                // --- find the first run after the bookmarkStart to copy its formatting
                $formatRun = null;
                $node = $bmStart->nextSibling;
                while ($node) {
                    if ($node->nodeName === 'w:r') {
                        $formatRun = $node;
                        break;
                    }
                    $node = $node->nextSibling;
                }

                // --- now remove everything up to the bookmarkEnd
                $toRemove = [];
                $node = $bmStart->nextSibling;
                while ($node) {
                    if ($node->nodeName === 'w:bookmarkEnd'
                        && $node->getAttribute('w:id') === $bmId) {
                        $bmEnd = $node;
                        break;
                    }
                    $toRemove[] = $node;
                    $node = $node->nextSibling;
                }
                foreach ($toRemove as $rem) {
                    $bmStart->parentNode->removeChild($rem);
                }

                // --- create a new run, copy formatting if available
                $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
                $newR = $dom->createElementNS($wNs, 'w:r');

                if ($formatRun) {
                    // clone its <w:rPr> (the first if >1)
                    $rPr = $xpath->query('.//w:rPr', $formatRun)->item(0);
                    if ($rPr) {
                        $rPrClone = $rPr->cloneNode(true);
                        $newR->appendChild($rPrClone);
                    }
                }

                // add your text
                $newT = $dom->createElementNS($wNs, 'w:t', htmlspecialchars($value));
                // preserve spaces exactly
                $newT->setAttribute('xml:space', 'preserve');
                $newR->appendChild($newT);

                // insert before bookmarkEnd
                $bmStart->parentNode->insertBefore($newR, $bmEnd);
            }
        }

        // save back
        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $contract = Contract::with([
                'template',
                'notaire',
                'clients.client',
                'attributes'
                ])->findOrFail($id);
            Log::info($contract);
            return response()->json(['contract' => $contract], 200);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
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
        Log::info($request);
        // Find the existing contract
        $contract = Contract::findOrFail($id);
        $template = ContractTemplate::find($request->contractType);

        // Get clients and buyers from request
        $clients = User::whereIn('id', array_column($request->clients, 'id'))->get();
        $buyers = User::whereIn('id', array_column($request->buyers, 'id'))->get();

        // Update contract record
        $contract->template_id = $template->id;
        $contract->notaire_id = $request->notaryOffice;
        $contract->save();

        // Sync attributes - delete old ones and create new ones
        ContractAttributes::where('contract_id', $contract->id)->delete();
        foreach ($request['attributes'] ?? [] as $attributeData) {
            ContractAttributes::create([
                'contract_id' => $contract->id,
                'name' => $attributeData['name'],
                'value' => $attributeData['value'],
            ]);
        }

        // Sync clients & buyers - delete old relationships first
        ContractClient::where('contract_id', $contract->id)->delete();
        foreach ($clients as $client) {
            ContractClient::create([
                'contract_id' => $contract->id,
                'client_id' => $client->id,
                'etat' => $request->clientType,
                'type' => 'PartA'
            ]);
        }
        foreach ($buyers as $buyer) {
            ContractClient::create([
                'contract_id' => $contract->id,
                'client_id' => $buyer->id,
                'etat' => $request->buyerType,
                'type' => 'PartB'
            ]);
        }

        // Only regenerate PDF if template or attributes changed
        if ($this->shouldRegeneratePdf($contract, $request)) {
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

            // Replace placeholders
            $processor = $this->replacePlaceholdersInTemplate(
                $processor,
                $template,
                $clients,
                $request['attributes'],
                $buyers,
                $request->notaryOffice
            );

            // Save the modified template
            $processor->saveAs($docxPath);

            // Convert to PDF
            $libreOfficeBin = env('LIBREOFFICE_BIN');
            $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir " .
                    escapeshellarg($pdfOutputDir) . ' ' . escapeshellarg($docxPath);
            $output = shell_exec($command . " 2>&1");
            \Log::error("LibreOffice conversion output: " . $output);

            // Handle the generated PDF
            $generatedPdfPath = "{$pdfOutputDir}/" . pathinfo($docxPath, PATHINFO_FILENAME) . ".pdf";
            if (File::exists($generatedPdfPath)) {
                // Delete old PDF if exists
                if ($contract->pdf_path) {
                    Storage::disk('public')->delete($contract->pdf_path);
                }
                File::move($generatedPdfPath, $pdfPath);
            }else {
                \Log::error('Le fichier PDF généré est introuvable à : ' . $generatedPdfPath);
                return response()->json([
                    'message' => 'Erreur : le fichier PDF n’a pas été généré correctement.',
                    'debugPath' => $generatedPdfPath
                ], 500);
            }

            // Update contract with new PDF path
            $contract->pdf_path = "contracts/{$pdfFileName}";
            $contract->save();

            // Cleanup
            File::delete($docxPath);
        }

        return response()->json([
            'message' => 'Contrat mis à jour avec succès!',
            'contract' => $contract,
            'pdfUrl' => $contract->pdf_path ? Storage::disk('public')->url($contract->pdf_path) : null
        ], 200);
    }

    /**
     * Determine if PDF needs to be regenerated
     */
    private function shouldRegeneratePdf($contract, $request)
    {
        // Check if template changed
        if ($contract->template_id != $request->contractType) {
            return true;
        }

        // Check if notary office changed
        if ($contract->notaire_id != $request->notaryOffice) {
            return true;
        }

        // Check if attributes changed (simplified check)
        $currentAttributes = $contract->attributes->pluck('value', 'name')->toArray();
        $newAttributes = collect($request['attributes'] ?? [])->pluck('value', 'name')->toArray();

        if ($currentAttributes != $newAttributes) {
            return true;
        }

        // Add more conditions as needed
        return false;

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
