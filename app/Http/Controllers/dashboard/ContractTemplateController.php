<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
    $template = ContractTemplate::with('contractType')->findOrFail($id);

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
        Log::info($request->all());
        $validated = $request->validate([
            'category_id' => 'required|exists:contract_types,id',
            'subcategory_name' => 'required|string|max:255',
            'taxe_pourcentage'=>'required|numeric',
            'taxe_type'=>'required',
            'attributes' => 'required|array',
            //'content' => 'required|string',
            'content' => 'required|file|mimes:docx|max:5120',
            'part_a_transformations' => 'required|array', // Will receive JSON string
            'part_b_transformations' => 'required|array', // Will receive JSON string
            'part_all_transformations' => 'required|array', // Will receive JSON string
        ]);

        $fileName = time() . '_' . $request->file('content')->getClientOriginalName();
        $path = $request->file('content')->storeAs('templates/contracts', $fileName, 'public');

        $template = ContractTemplate::create([
            'contract_type_id' => $validated['category_id'],
            'taxe_type' => $validated['taxe_type'],
            'taxe_pourcentage' => $validated['taxe_pourcentage'],
            'contract_subtype' => $validated['subcategory_name'],
            'attributes' => json_encode($validated['attributes']), // Now receives simple array
            'part_a_transformations' => json_encode($validated['part_a_transformations']),
            'part_b_transformations' => json_encode($validated['part_b_transformations']),
            'part_all_transformations' => json_encode($validated['part_all_transformations']),
            'content' => $path,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Contract Template created successfully',
            'data' => $template,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        Log::info($request->all());
        $validated = $request->validate([
            'category_id' => 'exists:contract_types,id',
            'subcategory_name' => 'string|max:255',
            'taxe_pourcentage'=>'numeric',
            'taxe_type'=>'string',
            'attributes' => 'array', // Will receive JSON string
            'part_a_transformations' => 'array', // Will receive JSON string
            'part_b_transformations' => 'array', // Will receive JSON string
            'part_all_transformations' => 'array', // Will receive JSON string
            'content' => 'nullable|file|mimes:docx|max:5120',
        ]);

        // Find the template to update
        $template = ContractTemplate::findOrFail($id);

        // Update the template
        $template->update([
            'contract_type_id' => $validated['category_id'],
            'contract_subtype' => $validated['subcategory_name'],
            'taxe_type' => $validated['taxe_type'],
            'taxe_pourcentage' => $validated['taxe_pourcentage'],
            'attributes' => $validated['attributes'], // Already JSON string from frontend
            'part_a_transformations' => $validated['part_a_transformations'], // Already JSON string
            'part_b_transformations' => $validated['part_b_transformations'], // Already JSON string
            'part_all_transformations' => $validated['part_all_transformations'], // Already JSON string
            'updated_by' => auth()->id(),
        ]);

        if($request->file('content')){
            Storage::disk('public')->delete($template->content);  //delete old template file
            $fileName = time() . '_' . $request->file('content')->getClientOriginalName();
            $path = $request->file('content')->storeAs('templates/contracts', $fileName, 'public');
            $template->content = $path;
            $template->save();
         }

        return response()->json([
            'message' => 'Contract Template updated successfully',
            'data' => $template,
        ]);
    }

    public function getAttributes(string $id)
    {
        try {
            $contractTemplate = ContractTemplate::findOrFail($id);

            // Decode the attributes if they're stored as JSON
            $attributes = json_decode($contractTemplate->attributes, true) ?? [];
            return response()->json([
                'success' => true,
                'attributes' => $attributes // Access the parameters array directly
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
}
