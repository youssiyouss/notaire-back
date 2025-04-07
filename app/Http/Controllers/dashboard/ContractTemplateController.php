<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use Illuminate\Support\Facades\Auth;


class ContractTemplateController extends Controller
{

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
}
