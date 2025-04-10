<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use Illuminate\Support\Facades\Auth;
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
        try {
            $template = ContractTemplate::with('contractType')->findOrFail($id);
            return response()->json(['template' => $template], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'User not found.'], 404);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:contract_types,id',
            'subcategory_name' => 'required|string|max:255',
            'attributes' => 'required|array',
            'transformations' => 'required|array',
            'content' => 'required|string',
        ]);

        $template = ContractTemplate::create([
            'contract_type_id' => $validated['category_id'],
            'contract_subtype' => $validated['subcategory_name'],
            'attributes' => json_encode($validated['attributes']), // Now receives simple array
            'pronoun_transformations' => json_encode($validated['transformations']),
            'content' => $validated['content'],
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Contract Template created successfully',
            'data' => $template,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'contract_type_id' => 'required|exists:contract_types,id',
            'contract_subtype' => 'required|string|max:255',
            'attributes' => 'required|string', // Will receive JSON string
            'pronoun_transformations' => 'required|string', // Will receive JSON string
            'content' => 'required|string',
        ]);

        // Find the template to update
        $template = ContractTemplate::findOrFail($id);

        // Update the template
        $template->update([
            'contract_type_id' => $validated['contract_type_id'],
            'contract_subtype' => $validated['contract_subtype'],
            'attributes' => $validated['attributes'], // Already JSON string from frontend
            'pronoun_transformations' => $validated['pronoun_transformations'], // Already JSON string
            'content' => $validated['content'],
            'updated_by' => auth()->id(),
        ]);

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
