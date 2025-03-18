<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractType;
use App\Models\ContractSubtype;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContractTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contractTypes = ContractType::with('subtypes')->get();
        return response()->json($contractTypes);
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
        try{

            $validator = Validator::make($request->all(), [
                'type_contrat' => 'required|string|max:255|unique:contract_types,name',
                'sous_type_contrats' => 'required|array',
                'sous_type_contrats.*' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $contractType = ContractType::create([
                'name' => $request->type_contrat,
                'created_by' =>  auth()->id()
            ]);

            foreach ($request->sous_type_contrats as $subtypeName) {
                ContractSubtype::create([
                    'contract_type_id' => $contractType->id,
                    'name' => $subtypeName,
                    'created_by' =>  auth()->id()
                ]);
            }

            return response()->json([
                'message' => 'Contract type and subtypes saved successfully',
                'contractType' => $contractType,
                'subtypes' => $contractType->subtypes
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
   public function destroy(string $id)
    {
        try {
            $contractType = ContractType::findOrFail($id);
            $contractType->subtypes()->delete(); // Supprimer d'abord les sous-types
            $contractType->delete(); // Puis supprimer le type de contrat

            return response()->json([
                'message' => __('Le type de contrat et ses sous-types ont été supprimés avec succès.')
            ], 200);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression du type de contrat: " . $e->getMessage());

            return response()->json([
                'message' => __('Une erreur est survenue lors de la suppression du type de contrat. Veuillez réessayer.')
            ], 500);
        }
    }

    /**
     * Supprimer un sous-type spécifique.
     */
    public function deleteSubType(string $id)
    {
        try {
            $subtype = ContractSubtype::findOrFail($id);
            $subtype->delete();

            return response()->json([
                'message' => __('Le sous-type de contrat a été supprimé avec succès.')
            ], 200);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression du sous-type de contrat: " . $e->getMessage());

            return response()->json([
                'message' => __('Une erreur est survenue lors de la suppression du sous-type de contrat. Veuillez réessayer.')
            ], 500);
        }
    }

    public function addSubType(Request $request, $contractTypeId)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $contractType = ContractType::findOrFail($contractTypeId);
            $subtype = $contractType->subtypes()->create([
                'name' => $request->name,
            ]);

            return response()->json([
                'message' => 'Sous-type ajouté avec succès',
                'subtype' => $subtype,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'ajout d'un sous-type : " . $e->getMessage());
            return response()->json(['error' => "Une erreur est survenue, veuillez réessayer."], 500);
        }
    }


    public function rename(Request $request, string $id)
    {
        try {
            Log::info($request->all());

            // Validate input
            $validator = Validator::make($request->all(), [
                'nouveauTitre' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('contract_types', 'name')->ignore($id),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Find and update contract type
            $type = ContractType::findOrFail($id);
            $type->name = $request-> nouveauTitre;
            $type->save();

            return response()->json([
                'type' => $type,
                'message' => __('Le titre de contrat a été renommé avec succès.')
            ], 200);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Erreur SQL: " . $e->getMessage());

            if ($e->getCode() == "23000") { // Handle unique constraint error
                return response()->json([
                    'errors' => [' nouveauTitre' => __('Ce nom de contrat existe déjà.')]
                ], 422);
            }

            return response()->json(['message' => __('Une erreur SQL est survenue.')], 500);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la modification du nom de contrat: " . $e->getMessage());

            return response()->json([
                'message' => __('Une erreur est survenue lors de la modification du nom de contrat. Veuillez réessayer.')
            ], 500);
        }
    }



}


