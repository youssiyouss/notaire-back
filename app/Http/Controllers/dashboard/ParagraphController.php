<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Paragraph;
use App\Models\ContractSubtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class ParagraphController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
           $paragraphs = Paragraph::orderBy('created_at', 'desc')->paginate(10); // Adjust per page limit

            // Group paragraphs by type
            $groupedParagraphs = $paragraphs->groupBy('type');

            return response()->json([
                'data' => $groupedParagraphs,
                'pagination' => [
                    'current_page' => $paragraphs->currentPage(),
                    'last_page' => $paragraphs->lastPage(),
                    'total' => $paragraphs->total(),
                    'per_page' => $paragraphs->perPage(),
                    'links' => $paragraphs->links(), // Optional: Get Laravel pagination links
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching paragraphs: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    public function getBySubcategory(string $subcategoryId)
    {
        try {
            $subtype = ContractSubtype::with('contractType')->findOrFail($subcategoryId);

            $paragraphs = Paragraph::where('contract_subtype_id', $subcategoryId)
                ->get()
                ->groupBy('type');

            return response()->json([
                'contract_type' => $subtype->contractType->name,  // Assuming 'name' is the type name
                'contract_subtype' => $subtype->name, // Assuming 'name' is the subtype name
                'paragraphs' => $paragraphs
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching paragraphs: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'subTypeId' => 'required|integer|exists:contract_subtypes,id',
                'title' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create paragraph
            $paragraph = Paragraph::create([
                'contract_subtype_id' => $request->subTypeId,
                'type' => $request->type,
                'title' => $request->title,
                'content' => $request->content,
                'created_by' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Paragraphe ajouté avec succès',
                'paragraph' => $paragraph
            ], 201);

        } catch (\Exception $e) {
            Log::error("Error storing paragraph: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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

        try {
            $paragraph = Paragraph::find($id);

            if (!$paragraph) {
                return response()->json(['message' => 'Paragraphe non trouvé'], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'subTypeId' => 'sometimes|integer|exists:contract_subtypes,id',
                'title' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Update paragraph fields
            $paragraph->update($request->only(['subTypeId', 'title', 'type', 'content']));

            return response()->json([
                'message' => 'Paragraphe mis à jour avec succès',
                'paragraph' => $paragraph
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error updating paragraph: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $paragraph = Paragraph::find($id);

            if (!$paragraph) {
                return response()->json(['message' => 'Paragraphe non trouvé'], 404);
            }

            $paragraph->delete();

            return response()->json(['message' => 'Paragraphe supprimé avec succès'], 200);

        } catch (\Exception $e) {
            Log::error("Error deleting paragraph: " . $e->getMessage());
            return response()->json(['message' => 'Erreur interne du serveur'], 500);
        }
    }
}
