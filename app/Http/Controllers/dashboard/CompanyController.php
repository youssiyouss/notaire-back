<?php

namespace App\Http\Controllers\dashboard;
use App\Http\Controllers\Controller;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $companies = Company::with('ownerUser','creator','editor')->paginate(20);

            return response()->json(['companies' => $companies], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
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
    public function store(StoreCompanyRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // Ajouter le user connecté comme créateur
            $data['created_by'] = Auth::id(); // ou Auth::user()->id

            // Gestion du logo
            if ($request->hasFile('logo')) {
                $picName = time() . '.' . $request->file('logo')->getClientOriginalName();
                $path = $request->file('logo')->storeAs('companies_logo', $picName, 'public');

                if (Storage::disk('public')->exists($path)) {
                    $data['logo'] = $path;
                } else {
                    return response()->json(['message' => 'Le fichier logo n’a pas pu être sauvegardé.'], 500);
                }
            }

            // Création de l'entreprise
            $company = Company::create($data);

            DB::commit();

            return response()->json([
                'message' => 'Entreprise créée avec succès.',
                'company' => $company,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de la création de l’entreprise', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création de l’entreprise.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        try {
            $company = Company::with('ownerUser')->findOrFail($company->id);
            return response()->json(['company' => $company], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Company not found.'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        try {
            // ✅ Mise à jour simple des champs
            $company->fill($request->only([
                'name',
                'nom_commercial',
                'email',
                'phone',
                'forme_juridique',
                'capital_social',
                'adresse_siege',
                'registre_commerce',
                'date_rc',
                'wilaya_rc',
                'nif',
                'nis',
                'boal',
                'date_creation',
                'ai',
                'activite_principale',
                'owner',
            ]));

            // ✅ Gestion du logo s’il est fourni
            if ($request->hasFile('logo')) {
                // Supprimer l'ancien logo si existe
                if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                    Storage::disk('public')->delete($company->logo);
                }

                // Stocker le nouveau logo
                $path = $request->file('logo')->store('companies_logo', 'public');
                $company->logo = $path;
            }
            $company->updated_by = Auth::user()->id;
            // ✅ Sauvegarder la société
            $company->save();

            return response()->json([
                'message' => 'Entreprise mise à jour avec succès',
                'company' => $company->load('ownerUser')
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'entreprise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        try {
            $company = Company::FindOrFail($company->id);
           /* if(!$company->logo && $company->logo != 'assets/images/default_avatar.png'){
                Storage::disk('public')->delete($company->logo);
            }*/
            $company->delete();

            return response()->json(['message' => 'Employee deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function search(Request $request)
    {
        $query = $request->get('query');

        $users = User::query()
            ->when($query, function ($q) use ($query) {
                $q->where('nom', 'like', "%$query%")
                ->orWhere('prenom', 'like', "%$query%")
                ->orWhere('email', 'like', "%$query%")
                ->orWhere('tel', 'like', "%$query%");
            })
            ->select('id', 'nom', 'prenom', 'email', 'date_de_naissance', 'picture')
            ->limit(10)
            ->get();

        return response()->json($users);
    }
}
