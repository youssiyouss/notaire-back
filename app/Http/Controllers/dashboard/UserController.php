<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Error;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $clients = User::where('role','client')->get();
            $members = User::where('role','admin')->get();

             return response()->json([
                'clients' => $clients,
                'members' => $members,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validation based on your schema
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'tel' => 'required|string|max:20|unique:users,tel', // Assuming the phone number will be alphanumeric
            'adresse' => 'nullable|string|max:255',
            'nationalite' => 'nullable|string|max:255',
            'sexe' => 'nullable|boolean',
            'date_de_naissance' => 'nullable|boolean',
            'lieu_de_naissance' => 'nullable|boolean',
            'nom_maternelle' => 'nullable|string|max:255',
            'prenom_mere' => 'nullable|string|max:255',
            'prenom_pere' => 'nullable|string|max:255',
            'numero_acte_naissance' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:client,admin', // Assuming roles are 'client' or 'admin'
            'type_carte' => 'nullable|string|max:255',
            'date_emission_carte' => 'nullable|date_format:Y-m-d',
            'lieu_emission_carte' => 'nullable|string|max:255',
            'emploi' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000',

        ]);

        try {
            // Creating the user
            $user = User::create([
                'name' => $validated['name'],
                'prenom' => $validated['prenom'],
                'email' => $validated['email'],
                'tel' => $validated['tel'],
                'adresse' => $validated['adresse'] ?? null,
                'nationalite' => $validated['nationalite'] ?? null,
                'sexe' => $validated['sexe'],
                'date_de_naissance' => $validated['date_de_naissance'],
                'lieu_de_naissance' => $validated['lieu_de_naissance'],
                'nom_maternelle' => $validated['nom_maternelle'] ?? null,
                'prenom_mere' => $validated['prenom_mere'] ?? null,
                'prenom_pere' => $validated['prenom_pere'] ?? null,
                'numero_acte_naissance' => $validated['numero_acte_naissance'] ?? null,
                'role' => $validated['role'] ?? 'client',
                'type_carte' => $validated['type_carte'] ?? 'identite',
                'date_emission_carte' => $validated['date_emission_carte'] ?? null,
                'lieu_emission_carte' => $validated['lieu_emission_carte'] ?? null,
                'emploi' => $validated['emploi'] ?? null,
                'password' => Hash::make($validated['password'])
            ]);

            return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Error creating user.'], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = User::findOrFail($id);
            return response()->json(['user' => $user], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'User not found.'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validation based on your schema
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'tel' => 'nullable|string|max:20|unique:users,tel,' . $id, // Allow updating the phone number for the specific user
            'adresse' => 'nullable|string|max:255',
            'nationalite' => 'nullable|string|max:255',
            'sexe' => 'nullable|boolean',
            'date_de_naissance' => 'nullable|boolean',
            'lieu_de_naissance' => 'nullable|boolean',
            'nom_maternelle' => 'nullable|string|max:255',
            'prenom_mere' => 'nullable|string|max:255',
            'prenom_pere' => 'nullable|string|max:255',
            'numero_acte_naissance' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:client,admin',
            'type_carte' => 'nullable|string|max:255',
            'date_emission_carte' => 'nullable|date_format:Y-m-d',
            'lieu_emission_carte' => 'nullable|string|max:255',
            'emploi' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'picture' => 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000',

        ]);

        try {
            $user = User::findOrFail($id);
            $user->update(array_filter($validated));  // Only update fields that are not null
            if($request->hasfile('picture')){
                $picName = $request->name.'.'.time().'.'.$request->file('picture')->getClientOriginalExtension();
                $membre->picture = $request->file('picture')->storeAs('avatars',$picName, 'public');
            }
            return response()->json(['message' => 'User updated successfully', 'user' => $user], 200);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'Error updating user.'], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::FindOrFail($id);
            if($user->picture){
                Storage::delete($user->picture);
            }
            $user->delete();

            Alert::success('تمام ','لقد تم حذف الحساب بنجاح');
            return redirect()->back();

        }catch (Exception $e) {
            Alert::error('Erreur ', $e->getMessage())->autoClose(false);
            return redirect()->back();
        }catch (Error $e) {
            Alert::error('Erreur ', $e->getMessage())->autoClose(false);
            return redirect()->back();
        }catch (\Throwable $e) {
            Alert::error('Erreur ', $e->getMessage())->autoClose(false);
            return redirect()->back();
        }

    }
}
