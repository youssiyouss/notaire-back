<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Client;
use App\Models\User;
use App\Models\Attribute;
use App\Models\AttributeValues;
use App\Models\ContractClient;
use App\Models\TemplateGroup;
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

            $query = $request->input('search');

            $users = User::with(['client', 'documents','companies'])
                            ->where('role', 'Client')
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
        // 1️⃣ Load template
        $template = ContractTemplate::find($request->contractType);
        // 2️⃣ Create the Contract record
        $contract = Contract::create([
            'template_id' => $template->id,
            'notaire_id'  => $request->notaryOffice,
            'status'      => 'Non Payé',
            'created_by'  => Auth::id(),
        ]);

        // 3️⃣ Persist each attribute + users per group
        foreach ($request->groups as $group) {
            // Attributes
            foreach ($group['attributes'] as $attr) {
                $attributeModel = Attribute::where('attribute_name', $attr['name'])
                    ->where('group_id', $group['group_id'])
                    ->first();

                if ($attributeModel) {
                    AttributeValues::create([
                        'contract_id'  => $contract->id,
                        'attribute_id' => $attributeModel->id,
                        'value'        => $attr['value'],
                    ]);
                }
            }

            // Users
            foreach ($group['users'] as $user) {
                ContractClient::create([
                    'contract_id' => $contract->id,
                    'client_id'   => $user['id'],
                    'type'        => $group['group_name'],
                ]);
            }
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
        foreach ($request->groups as $group) {
            foreach ($group['attributes'] as $attr) {
                $replacements[$attr['name']] = $attr['value'];
            }

            // 🔁 Récupérer le modèle du groupe depuis la base
            $groupModel = TemplateGroup::with('wordTransformations')->find($group['group_id']);

            if ($groupModel && $groupModel->wordTransformations) {
                foreach ($groupModel->wordTransformations as $trans) {
                    $value = $this->determinePronounForm($trans, $group['users']);
                    $replacements[$trans->placeholder] = $value;
                }
            }
        }

        $notary = User::find($request->notaryOffice);
        if ($notary) {
            $notaryFullName = $notary->nom . ' ' . $notary->prenom;
            $replacements['اسم_الموثق'] = $notaryFullName;
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

        // 9️⃣ Verify PDF exists and update DB
        $generatedPdfPath = storage_path("app/public/contracts/pdf/") . pathinfo($tempDocxPath, PATHINFO_FILENAME) . ".pdf";
        if (File::exists($generatedPdfPath)) {
            File::move($generatedPdfPath, $pdfFinalPath);
            $contract->update([
                'pdf_path'  => "contracts/pdf/{$pdfFileName}"
            ]);
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
            if (strtolower($user['sexe']) === 'male') {
                $maleCount++;
            } else {
                $femaleCount++;
            }
        }

        if ($maleCount > 0 && $femaleCount === 0) {
            return $maleCount > 1 ? $transformation->masculine_plural : $transformation->masculine;
        } elseif ($femaleCount > 0 && $maleCount === 0) {
            return $femaleCount > 1 ? $transformation->feminine_plural : $transformation->feminine;
        } else {
            return $transformation->masculine_plural;
        }
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
                'attributes',
                'creator',
                'editor'
                ])->findOrFail($id);

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
        try {
            $contract = Contract::with([
                'template.groups.attributes',
                'attributes.attribute.group',
                'clients.client',
                'clientUsers.client'
                ])->findOrFail($id);

            return response()->json(['contract' => $contract], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Log::info($request);

        $contract = Contract::findOrFail($id);
        $template = ContractTemplate::findOrFail($request->contractType);

        // 🟢 Update base contract fields
        $contract->template_id = $template->id;
        $contract->notaire_id = $request->notaryOffice;
        $contract->save();

        // 🔁 Clean old data
        AttributeValues::where('contract_id', $contract->id)->delete();
        ContractClient::where('contract_id', $contract->id)->delete();

        // 🆕 Insert new attributes + users by group
        foreach ($request->groups as $group) {
            // Insert attributes
            foreach ($group['attributes'] as $attr) {
                $attributeModel = Attribute::where('attribute_name', $attr['name'])
                    ->where('group_id', $group['group_id'])
                    ->first();

                if ($attributeModel) {
                    AttributeValues::create([
                        'contract_id'  => $contract->id,
                        'attribute_id' => $attributeModel->id,
                        'value'        => $attr['value'],
                    ]);
                }
            }

            // Insert users
            foreach ($group['users'] as $user) {
                ContractClient::create([
                    'contract_id' => $contract->id,
                    'client_id'   => $user['id'],
                    'type'        => $group['group_name'],
                ]);
            }
        }

        // 🔁 Regenerate files only if needed
        if ($this->shouldRegeneratePdf($contract, $request)) {

            // 📁 Paths setup
            $templatePath  = storage_path("app/public/{$template->content}");
            $uniqueId      = uniqid();
            $tempDocxPath  = storage_path("app/tmp_contract_{$uniqueId}.docx");
            $pdfFileName   = "contract_{$contract->id}_" . time() . ".pdf";
            $docxFileName  = "contract_{$contract->id}_" . time() . ".docx";
            $pdfFinalPath  = storage_path("app/public/contracts/pdf/{$pdfFileName}");
            $docxFinalPath = storage_path("app/public/contracts/word/{$docxFileName}");

            Storage::disk('public')->makeDirectory('contracts/pdf');
            Storage::disk('public')->makeDirectory('contracts/word');

            // 📌 Placeholder replacements
            $replacements = [];

            foreach ($request->groups as $group) {
                foreach ($group['attributes'] as $attr) {
                    $replacements[$attr['name']] = $attr['value'];
                }

                $groupModel = TemplateGroup::with('wordTransformations')->find($group['group_id']);
                if ($groupModel && $groupModel->wordTransformations) {
                    foreach ($groupModel->wordTransformations as $trans) {
                        $value = $this->determinePronounForm($trans, $group['users']);
                        $replacements[$trans->placeholder] = $value;
                    }
                }
            }

            // 👤 Nom du notaire
            $notary = User::find($request->notaryOffice);
            if ($notary) {
                $replacements['اسم_الموثق'] = $notary->nom . ' ' . $notary->prenom;
            }

            // 🧠 Injection DOCX
            $this->injectBookmarks($templatePath, $tempDocxPath, $replacements);

            // 📝 Enregistrer la version Word
            File::copy($tempDocxPath, $docxFinalPath);
            $contract->update(['word_path' => "contracts/word/{$docxFileName}"]);

            // 🔄 Convertir en PDF
            $libreOfficeBin = env('LIBREOFFICE_BIN');
            $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir "
                . escapeshellarg(storage_path("app/public/contracts/pdf")) . ' '
                . escapeshellarg($tempDocxPath)
                . " 2>&1";
            $output = shell_exec($command);

            $generatedPdfPath = storage_path("app/public/contracts/pdf/") . pathinfo($tempDocxPath, PATHINFO_FILENAME) . ".pdf";

            if (File::exists($generatedPdfPath)) {
                File::move($generatedPdfPath, $pdfFinalPath);
                $contract->update(['pdf_path' => "contracts/pdf/{$pdfFileName}"]);
            } else {
                \Log::error("PDF not found: " . $generatedPdfPath);
                return response()->json([
                    'message' => 'Erreur : PDF introuvable après la génération.',
                    'commandOutput' => $output,
                    'pathTried' => $generatedPdfPath,
                ], 500);
            }

            // 🧹 Nettoyage
            File::delete($tempDocxPath);
        }

        return response()->json([
            'message' => 'Contrat mis à jour avec succès!',
            'contract' => $contract
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

    public function summarize(string $id)
    {
        try {
            \Log::info("Starting summary generation for contract ID: {$id}");

            // 1. Load the contract with relations
            $contract = Contract::with(['template.groups.attributes', 'attributes.attribute', 'notaire'])->find($id);

            if (!$contract) {
                \Log::error("Contract not found with ID: {$id}");
                return response()->json(['error' => 'Contract not found'], 404);
            }

            \Log::info("Contract found, loading template and notary");

            $template = $contract->template;
            $notary = $contract->notaire;
            $libreOfficeBin = env('LIBREOFFICE_BIN');

            // 2. Validate template and summary file
            if (!$template->summary_path) {
                \Log::error("No summary path defined in template for contract: {$id}");
                return response()->json(['error' => 'Summary template path not configured'], 400);
            }

            $summaryTemplatePath = storage_path("app/public/{$template->summary_path}");

            if (!File::exists($summaryTemplatePath)) {
                \Log::error("Summary template file not found at path: {$summaryTemplatePath}");
                return response()->json([
                    'error' => "Summary template file not found",
                    'path' => $template->summary_path
                ], 404);
            }

            \Log::info("Summary template found at: {$summaryTemplatePath}");

            // 3. Prepare directories and file paths
            try {
                Storage::disk('public')->makeDirectory('contracts/summary/pdf');
                Storage::disk('public')->makeDirectory('contracts/summary/word');
            } catch (\Exception $e) {
                \Log::error("Failed to create summary directories: " . $e->getMessage());
                return response()->json(['error' => 'Failed to create output directories'], 500);
            }

            $summaryDocxName = "summary_contract_{$contract->id}.docx";
            $summaryPdfName = "summary_contract_{$contract->id}.pdf";
            $summaryTempDocxPath = storage_path("app/temp_summary_contract_{$contract->id}.docx");
            $summaryDocxFinal = storage_path("app/public/contracts/summary/word/{$summaryDocxName}");
            $summaryPdfFinal = storage_path("app/public/contracts/summary/pdf/{$summaryPdfName}");

            // 4. Prepare replacements
            $summaryReplacements = [];

            // Process attribute values
            $attributeValues = $contract->attributes;
            foreach ($attributeValues as $attrValue) {
                if ($attrValue->attribute) {
                    $summaryReplacements[$attrValue->attribute->attribute_name] = $attrValue->value;
                }
            }

            // Process group transformations
            foreach ($template->groups as $group) {
                $groupUsers = $contract->clients()
                    ->where('type', $group->name)
                    ->with('client')
                    ->get();

                if ($group->wordTransformations) {
                    foreach ($group->wordTransformations as $trans) {
                        $value = $this->determinePronounForm($trans, $groupUsers);
                        $summaryReplacements[$trans->placeholder] = $value;
                    }
                }
            }

            // Add notary information if exists
            if ($notary) {
                $summaryReplacements['اسم_الموثق'] = $notary->nom . ' ' . $notary->prenom;
            }

           // \Log::info("Prepared replacements: " . json_encode($summaryReplacements));

            // 5. Process the document
            try {
                $this->injectBookmarks($summaryTemplatePath, $summaryTempDocxPath, $summaryReplacements);
            } catch (\Exception $e) {
                \Log::error("Failed to inject bookmarks: " . $e->getMessage());
                return response()->json(['error' => 'Failed to process document template'], 500);
            }

            // 6. Save final docx
            try {
                File::copy($summaryTempDocxPath, $summaryDocxFinal);
            } catch (\Exception $e) {
                \Log::error("Failed to save final DOCX: " . $e->getMessage());
                return response()->json(['error' => 'Failed to save document'], 500);
            }

            // 7. Convert to PDF
            if (!$libreOfficeBin || !file_exists($libreOfficeBin)) {
                \Log::error("LibreOffice binary not found at: {$libreOfficeBin}");
                return response()->json(['error' => 'PDF conversion tool not configured'], 500);
            }

            $summaryCommand = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir " .
                escapeshellarg(storage_path("app/public/contracts/summary/pdf")) . ' ' .
                escapeshellarg($summaryTempDocxPath) . " 2>&1";

            $summaryOutput = shell_exec($summaryCommand);

            // 8. Verify and move PDF
            $generatedPdfBaseName = pathinfo($summaryTempDocxPath, PATHINFO_FILENAME);
            $generatedSummaryPdfPath = storage_path("app/public/contracts/summary/pdf/{$generatedPdfBaseName}.pdf");

            if (!File::exists($generatedSummaryPdfPath)) {
                return response()->json(['error' => 'PDF conversion failed'], 500);
            }

            try {
                File::move($generatedSummaryPdfPath, $summaryPdfFinal);
            } catch (\Exception $e) {
                \Log::error("Failed to move PDF: " . $e->getMessage());
                return response()->json(['error' => 'Failed to save PDF'], 500);
            }

            // 9. Update contract record
            try {
                $contract->update([
                    'summary_word_path' => "contracts/summary/word/{$summaryDocxName}",
                    'summary_pdf_path' => "contracts/summary/pdf/{$summaryPdfName}"
                ]);

                $contract->save();
            } catch (\Exception $e) {
                \Log::error("Failed to update contract record: " . $e->getMessage());
                // Continue despite this error as files were generated
            }

            // 10. Clean up
            try {
                File::delete($summaryTempDocxPath);
            } catch (\Exception $e) {
                \Log::error("Failed to delete temp file: " . $e->getMessage());
                // Not critical, just log
            }

            return response()->json([
                'message' => 'Summary generated successfully',
                'summary_pdf' => $contract->summary_pdf_path,
                'summary_word' => $contract->summary_word_path
            ], 201);

        } catch (\Throwable $e) {
            \Log::error("Unexpected error in summarize: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    protected function injectBookmarks($sourceDocx, $targetDocx, array $replacements)
    {
        if (!copy($sourceDocx, $targetDocx)) {
            \Log::error("Failed to copy template file");
            throw new \Exception("Failed to copy template file");
        }

        $zip = new \ZipArchive();
        if ($zip->open($targetDocx) !== true) {
            \Log::error("Cannot open DOCX file: {$targetDocx}");
            throw new \Exception("Cannot open DOCX file");
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (!$xml) {
                throw new \Exception("Could not read document.xml from DOCX");
            }

            // Force UTF-8 encoding
            $xml = mb_convert_encoding($xml, 'UTF-8', mb_detect_encoding($xml));
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;

            // Important: Load XML with UTF-8 encoding options
            if (!$dom->loadXML($xml, LIBXML_NOENT | LIBXML_NONET | LIBXML_PARSEHUGE)) {
                throw new \Exception("Failed to parse document.xml");
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            foreach ($replacements as $name => $value) {
                try {
                    // Ensure bookmark name is properly encoded for XPath
                    $encodedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $query = "//w:bookmarkStart[@w:name='{$encodedName}']";
                    $starts = $xpath->query($query);

                    if ($starts->length === 0) {
                        continue;
                    }

                    foreach ($starts as $bmStart) {
                        $bmId = $bmStart->getAttribute('w:id');
                        $formatRun = null;
                        $node = $bmStart->nextSibling;

                        // Find first run after bookmarkStart for formatting
                        while ($node) {
                            if ($node->nodeName === 'w:r') {
                                $formatRun = $node;
                                break;
                            }
                            $node = $node->nextSibling;
                        }

                        // Remove content between bookmarkStart and bookmarkEnd
                        $toRemove = [];
                        $node = $bmStart->nextSibling;
                        while ($node) {
                            if ($node->nodeName === 'w:bookmarkEnd' && $node->getAttribute('w:id') === $bmId) {
                                $bmEnd = $node;
                                break;
                            }
                            $toRemove[] = $node;
                            $node = $node->nextSibling;
                        }

                        foreach ($toRemove as $rem) {
                            $bmStart->parentNode->removeChild($rem);
                        }

                        // Create new run with value
                        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
                        $newR = $dom->createElementNS($wNs, 'w:r');

                        if ($formatRun) {
                            $rPr = $xpath->query('.//w:rPr', $formatRun)->item(0);
                            if ($rPr) {
                                $rPrClone = $rPr->cloneNode(true);
                                $newR->appendChild($rPrClone);
                            }
                        }

                        $newT = $dom->createElementNS($wNs, 'w:t', htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                        $newT->setAttribute('xml:space', 'preserve');
                        $newR->appendChild($newT);
                        $bmStart->parentNode->insertBefore($newR, $bmEnd);
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed processing bookmark '{$name}': " . $e->getMessage());
                    continue; // Skip this bookmark but continue with others
                }
            }

            // Save back with UTF-8 encoding
            $updatedXml = $dom->saveXML();
            if (!$zip->addFromString('word/document.xml', $updatedXml)) {
                throw new \Exception("Failed to update document.xml in ZIP");
            }

            $zip->close();

        } catch (\Exception $e) {
            $zip->close();
            \Log::error("Bookmark injection failed: " . $e->getMessage());
            throw new \Exception("Failed to process document template: " . $e->getMessage());
        }
    }

}
