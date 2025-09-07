<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Attribute;
use App\Models\TemplateGroup;
use App\Models\WordTransformation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Error;
use Exception;

class ContractTemplateController extends Controller
{

    public function index()
    {
            $templates = ContractTemplate::with('contractType')->get();
            return response()->json([
                'success' => true,
                'templates' => $templates // Access the parameters array directly
            ], 200);

    }

    public function show(string $id)
    {
        $template = ContractTemplate::with([
            'contractType',
            'groups.attributes',
            'groups.wordTransformations'
        ])->findOrFail($id);

        // Chemin du fichier .docx à convertir
        $docxPath = Storage::disk('public')->path($template->content);

        // Récupère le nom de base du fichier sans extension
        $pdfName = pathinfo($template->content, PATHINFO_FILENAME) . '.pdf';

        // Chemin complet du fichier PDF généré
        $pdfPath = storage_path('app/public/templates/pdf_previews' . $pdfName);

        // Commande pour convertir le fichier .docx en PDF avec LibreOffice
        $libreOfficeBin = env('LIBREOFFICE_BIN');
        $command = "\"{$libreOfficeBin}\" --headless --convert-to pdf --outdir " . escapeshellarg(storage_path('app/public/templates/pdf_previews')) . " " . escapeshellarg($docxPath);

        // Exécution de la commande
        $output = shell_exec($command . " 2>&1");

        // Retourner le lien du PDF généré
        return response()->json([
            'template' => $template,
            'pdf_url' => asset('storage/templates/pdf_previews/' . $pdfName) // Retourne le lien correct du fichier PDF
        ], 200);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:contract_types,id',
            'subcategory_name' => 'required|string|max:255',
            'taxe_pourcentage' => 'required|numeric',
            'taxe_type' => 'required|string',
            'content' => 'required|file|mimes:docx|max:51200',
            'transformGroups' => 'required|array',
            'transformGroups.*.groupName' => 'required|string',
            'transformGroups.*.attributes' => 'required|array',
            'transformGroups.*.attributes.*.name' => 'required|string',
            'transformGroups.*.attributes.*.source_field' => 'required|string',
            'transformGroups.*.transformations' => 'required|array',
            'transformGroups.*.transformations.*.placeholder' => 'required|string',
            'transformGroups.*.transformations.*.masculine' => 'required|string',
            'transformGroups.*.transformations.*.feminine' => 'required|string',
            'transformGroups.*.transformations.*.masculine_plural' => 'required|string',
            'transformGroups.*.transformations.*.feminine_plural' => 'required|string',
            'original' => 'nullable|numeric',
            'copy' => 'nullable|numeric',
            'documentation' => 'nullable|numeric',
            'publication' => 'nullable|numeric',
            'consultation' => 'nullable|numeric',
            'consultationFee' => 'nullable|numeric',
            'workFee' => 'nullable|numeric',
            'others' => 'nullable|numeric',
            'stamp' => 'nullable|numeric',
            'registration' => 'nullable|numeric',
            'advertisement' => 'nullable|numeric',
            'rkm' => 'nullable|numeric',
            'announcements' => 'nullable|numeric',
            'deposit' => 'nullable|numeric',
            'boal' => 'nullable|numeric',
            'registration_or_cancellation' => 'nullable|numeric',
        ]);

        $fileName = time() . '_' . $request->file('content')->getClientOriginalName();
        $path = $request->file('content')->storeAs('templates/contracts', $fileName, 'public');


        $template = ContractTemplate::create([
            'contract_type_id' => $validated['category_id'],
            'contract_subtype' => $validated['subcategory_name'],
            'taxe_type' => $validated['taxe_type'],
            'taxe_pourcentage' => $validated['taxe_pourcentage'],
            'content' => $path,
            'created_by' => auth()->id(),
            'original' => $validated['original'] ?? null,
            'copy' => $validated['copy'] ?? null,
            'documentation' => $validated['documentation'] ?? null,
            'publication' => $validated['publication'] ?? null,
            'consultation' => $validated['consultation'] ?? null,
            'consultationFee' => $validated['consultationFee'] ?? null,
            'workFee' => $validated['workFee'] ?? null,
            'others' => $validated['others'] ?? null,
            'stamp' => $validated['stamp'] ?? null,
            'registration' => $validated['registration'] ?? null,
            'advertisement' => $validated['advertisement'] ?? null,
            'rkm' => $validated['rkm'] ?? null,
            'announcements' => $validated['announcements'] ?? null,
            'deposit' => $validated['deposit'] ?? null,
            'boal' => $validated['boal'] ?? null,
            'registration_or_cancellation' => $validated['registration_or_cancellation'] ?? null,
        ]);


        foreach ($validated['transformGroups'] as $groupData) {
            // Create the group
            $group = TemplateGroup::create([
                'template_id' => $template->id,
                'name' => $groupData['groupName'],
            ]);

            // Store attributes
            foreach ($groupData['attributes'] as $attributeData) {
                Attribute::create([
                    'group_id' => $group->id,
                    'attribute_name' => $attributeData['name'],
                    'source_field' => $attributeData['source_field']
                ]);
            }

            // Store transformations
            foreach ($groupData['transformations'] as $trans) {
                WordTransformation::create([
                    'group_id' => $group->id,
                    'placeholder' => $trans['placeholder'],
                    'masculine' => $trans['masculine'],         // Now matches frontend
                    'feminine' => $trans['feminine'],
                    'masculine_plural' => $trans['masculine_plural'],
                    'feminine_plural' => $trans['feminine_plural']

                ]);
            }
        }

        return response()->json([
            'message' => 'Contract Template created successfully'
        ], 201);

    }

    public function update(Request $request, $id)
    {
        Log::info($request->all());
        $validated = $request->validate([
            'contract_type_id' => 'required|exists:contract_types,id',
            'contract_subtype' => 'required|string|max:255',
            'taxe_pourcentage' => 'required|numeric',
            'taxe_type' => 'required|string',
            'content' => 'nullable|file|mimes:docx|max:51200',
            'groups' => 'required|array',
            'groups.*.name' => 'required|string',
            'groups.*.attributes' => 'required|array',
            'groups.*.attributes.*.attribute_name' => 'required|string',
            'groups.*.attributes.*.source_field' => 'required|string',
            'groups.*.transformations' => 'required|array',
            'groups.*.transformations.*.placeholder' => 'required|string',
            'groups.*.transformations.*.masculine' => 'required|string',
            'groups.*.transformations.*.feminine' => 'required|string',
            'groups.*.transformations.*.masculine_plural' => 'required|string',
            'groups.*.transformations.*.feminine_plural' => 'required|string',
            'original' => 'nullable|numeric',
            'copy' => 'nullable|numeric',
            'documentation' => 'nullable|numeric',
            'publication' => 'nullable|numeric',
            'consultation' => 'nullable|numeric',
            'consultationFee' => 'nullable|numeric',
            'workFee' => 'nullable|numeric',
            'others' => 'nullable|numeric',
            'stamp' => 'nullable|numeric',
            'registration' => 'nullable|numeric',
            'advertisement' => 'nullable|numeric',
            'rkm' => 'nullable|numeric',
            'announcements' => 'nullable|numeric',
            'deposit' => 'nullable|numeric',
            'boal' => 'nullable|numeric',
            'registration_or_cancellation' => 'nullable|numeric',
        ]);

        // Find the template to update
        $template = ContractTemplate::findOrFail($id);
        DB::beginTransaction();
        try{
            // Update the template
            $template->update([
                'contract_type_id' => $validated['contract_type_id'],
                'contract_subtype' => $validated['contract_subtype'],
                'taxe_type' => $validated['taxe_type'],
                'taxe_pourcentage' => $validated['taxe_pourcentage'],
                'updated_by' => auth()->id(),
                'original' => $validated['original'] ?? null,
                'copy' => $validated['copy'] ?? null,
                'documentation' => $validated['documentation'] ?? null,
                'publication' => $validated['publication'] ?? null,
                'consultation' => $validated['consultation'] ?? null,
                'consultationFee' => $validated['consultationFee'] ?? null,
                'workFee' => $validated['workFee'] ?? null,
                'others' => $validated['others'] ?? null,
                'stamp' => $validated['stamp'] ?? null,
                'registration' => $validated['registration'] ?? null,
                'advertisement' => $validated['advertisement'] ?? null,
                'rkm' => $validated['rkm'] ?? null,
                'announcements' => $validated['announcements'] ?? null,
                'deposit' => $validated['deposit'] ?? null,
                'boal' => $validated['boal'] ?? null,
                'registration_or_cancellation' => $validated['registration_or_cancellation'] ?? null,
            ]);

            if($request->file('content')){
                Storage::disk('public')->delete($template->content);  //delete old template file
                $fileName = time() . '_' . $request->file('content')->getClientOriginalName();
                $path = $request->file('content')->storeAs('templates/contracts', $fileName, 'public');
                $template->content = $path;
                $template->save();
            }


           // Get existing groups with their relationships
            $existingGroups = $template->groups()->with(['attributes', 'wordTransformations'])->get()->keyBy('name');
            $newGroups = collect($validated['groups']);

            // Process groups
            foreach ($newGroups as $newGroupData) {
                $groupName = $newGroupData['name'];

                // Find or create group
                $group = $existingGroups->get($groupName) ?? $template->groups()->create(['name' => $groupName]);

                // Process attributes
                $this->syncGroupRelations(
                    $group->attributes(),
                    $newGroupData['attributes'],
                    'attribute_name',
                    ['attribute_name', 'source_field']
                );

                // Process transformations
                $this->syncGroupRelations(
                    $group->wordTransformations(),
                    $newGroupData['transformations'],
                    'placeholder',
                    ['placeholder', 'masculine', 'feminine', 'masculine_plural', 'feminine_plural']
                );
            }

            // Delete groups that were removed
            $groupsToKeep = $newGroups->pluck('name');
            $template->groups()
                ->whereNotIn('name', $groupsToKeep)
                ->delete();

            DB::commit();

            return response()->json([
                'message' => 'Contract Template updated successfully',
                'data' => $template->load(['groups.attributes', 'groups.wordTransformations']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating contract template: ' . $e->getMessage());
            return response()->json(['message' => 'Update failed'], 500);
        }
    }

    /**
     * Helper method to sync group relationships
     */
    protected function syncGroupRelations($relation, $newItems, $identifierField, $fieldsToUpdate)
    {
        $existingItems = $relation->get()->keyBy($identifierField);
        $newItems = collect($newItems);

        // Update or create items
        foreach ($newItems as $newItemData) {
            $identifier = $newItemData[$identifierField];
            $itemData = array_intersect_key($newItemData, array_flip($fieldsToUpdate));

            if ($existingItem = $existingItems->get($identifier)) {
                $existingItem->update($itemData);
            } else {
                $relation->create($itemData);
            }
        }

        // Delete items that were removed
        $identifiersToKeep = $newItems->pluck($identifierField);
        $relation->whereNotIn($identifierField, $identifiersToKeep)->delete();
    }

    public function getGroups(string $id)
    {
        try {
            $template = ContractTemplate::with([
                'contractType',
                'groups'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'template' => $template // Access the parameters array directly
            ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(string $id)
    {
        try {
            $ContractTemplate = ContractTemplate::FindOrFail($id);

            $ContractTemplate->delete();

            return response()->json(['message' => 'Employee deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function uploadSummary(Request $request)
    {
        $request->validate([
            'summary_file' => 'required|mimes:doc,docx|max:2048',
            'template_id' => 'required|exists:contract_templates,id'
        ]);

        $template = ContractTemplate::findOrFail($request->template_id);

        if ($request->hasFile('summary_file')) {
            $fileName = time() . '_' . $request->file('summary_file')->getClientOriginalName();
            $path = $request->file('summary_file')->storeAs('templates/template_summaries', $fileName, 'public');

            $template->summary_path = $path;
            $template->save();
        }

        return response()->json(['message' => 'Résumé ajouté avec succès.']);
    }

    public function deleteSummary($id)
    {

        $template = ContractTemplate::findOrFail($id);

        if($template->summary_path){
            Storage::disk('public')->delete($template->summary_path);
        }
        $template->summary_path=null;
        $template->save();

        return response()->json(['message' => 'Résumé supprimé avec succès.']);
    }


    public function duplicate(Request $request, $id)
    {
        $request->validate([
            'contract_subtype' => 'required|string|max:255|unique:contract_templates'
        ]);

        // Trouver le template d’origine avec ses relations
        $original = ContractTemplate::with(['groups.attributes','groups.wordTransformations'])->findOrFail($id);
        Log::info($original);
        // Créer le nouveau template
        $newTemplate = ContractTemplate::create([
            'contract_type_id' => $original->contract_type_id,
            'contract_subtype' => $request->contract_subtype, // remplacé
            'taxe_type' => $original->taxe_type,
            'taxe_pourcentage' => $original->taxe_pourcentage,
            'content' => $original->content, // fichier vide
            'created_by' => auth()->id(),
            'original' => $original->original,
            'copy' => $original->copy,
            'documentation' => $original->documentation,
            'publication' => $original->publication,
            'consultation' => $original->consultation,
            'consultationFee' => $original->consultationFee,
            'workFee' => $original->workFee,
            'others' => $original->others,
            'stamp' => $original->stamp,
            'registration' => $original->registration,
            'advertisement' => $original->advertisement,
            'rkm' => $original->rkm,
            'announcements' => $original->announcements,
            'deposit' => $original->deposit,
            'boal' => $original->boal,
            'registration_or_cancellation' => $original->registration_or_cancellation,
        ]);

        // Dupliquer les groupes
        foreach ($original->groups as $group) {
            Log::info($group);
            $newGroup = TemplateGroup::create([
                'template_id' => $newTemplate->id,
                'name' => $group->name,
            ]);

            // Dupliquer attributs
            foreach ($group->attributes as $attribute) {
                Attribute::create([
                    'group_id' => $newGroup->id,
                    'attribute_name' => $attribute->attribute_name,
                    'source_field' => $attribute->source_field,
                ]);
            }

            // Dupliquer transformations
            foreach ($group->wordTransformations as $trans) {
                WordTransformation::create([
                    'group_id' => $newGroup->id,
                    'placeholder' => $trans->placeholder,
                    'masculine' => $trans->masculine,
                    'feminine' => $trans->feminine,
                    'masculine_plural' => $trans->masculine_plural,
                    'feminine_plural' => $trans->feminine_plural,
                ]);
            }
        }

        return response()->json([
            'message' => 'Contract Template duplicated successfully',
            'template' => $newTemplate->load([ 'contractType','groups.attributes','groups.wordTransformations'])
        ], 201);
    }

}
