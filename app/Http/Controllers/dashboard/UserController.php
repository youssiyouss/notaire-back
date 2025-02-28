<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Error;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $clients = User::where('role','Client')->paginate(20);
            $members = User::where('role','!=','Client')->paginate(20);

            return response()->json([
                'clients' => $clients,
                'members' => $members,
           ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try{
            // Validate the basic registration data
            $validator =$request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'tel' => 'required|regex:/^\+?[0-9]\d{0,14}$/|unique:users',
                'adresse' =>'required|string|max:600',
                'password' => 'required|string|min:8|confirmed',
                'sexe'=>'required|',
                'date_de_naissance'=>'required|date',
                'role'=>'required|string',
                'ccp'=>'nullable|string',
                'date_virement_salaire'=>'nullable|date',
                'salaire'=>'nullable|numeric',
                'picture' => 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000'
            ]);

            // Create the user with basic information
            $user = new User();
            $user->nom = $request->nom;
            $user->prenom = $request->prenom;
            $user->email = $request->email;
            $user->tel = $request->tel;
            $user->password = Hash::make($request->password);
            $user->adresse= $request->adresse;
            $user->role =  $request->role;
            $user->sexe=$request->sexe;
            $user->date_de_naissance=$request->date_de_naissance;
            $user->salaire=$request->salaire;
            $user->ccp=$request->ccp;
            $user->date_virement_salaire=$request->date_virement_salaire;

            if ($request->hasFile('picture')) {
                $picName = $request->nom . '.' . time() . '.' . $request->file('picture')->getClientOriginalExtension();
                // Store the picture in the 'avatars' folder
                $path = $request->file('picture')->storeAs('avatars', $picName, 'public');

                // Check if the file is saved
                if (Storage::disk('public')->exists('avatars/' . $picName)) {
                    $user->picture = $path;
                } else {
                    return response()->json(['message' => 'File could not be saved'], 500);
                }
            }

            $user->save();
            // Return success response
            return response()->json(['message' => 'Employee registered successfully'], 201);

        }catch(\Throwable $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }catch (\Exception $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
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
    public function update(Request $request,$id)
    {
        Log::info($request);
        // Validation based on your schema
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'tel' => 'nullable|regex:/^\+?[0-9]\d{0,14}$/|max:20|unique:users,tel,' . $id, // Allow updating the phone number for the specific user
            'adresse' =>'required|string|max:600',
            'sexe'=>'required|',
            'date_de_naissance'=>'required|date',
            'role'=>'nullable|string',
            'ccp'=>'nullable|string',
            'salaire'=>'nullable|numeric',
            'date_virement_salaire'=>'nullable|date',
            'picture'=> 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000',

        ]);

        try {
            $user = User::findOrFail($id);
            $requestData = $request->all();
            Log::info($user->role);
            // Vérifier chaque champ et garder l'ancienne valeur si non fourni
            $user->nom = $requestData['nom'] ?? $user->nom;
            $user->prenom = $requestData['prenom'] ?? $user->prenom;
            $user->email = $requestData['email'] ?? $user->email;
            $user->tel = $requestData['tel'] ?? $user->tel;
            $user->adresse = $requestData['adresse'] ?? $user->adresse;
            $user->sexe = $requestData['sexe'] ?? $user->sexe;
            $user->role = $requestData['role'] ?? $user->role;
            $user->date_de_naissance = $requestData['date_de_naissance'] ?? $user->date_de_naissance;
            $user->ccp= $requestData['ccp'] ?? $user->ccp;
            $user->salaire=$requestData['salaire'] ?? $user->salaire;
            $user->date_virement_salaire=$requestData['date_virement_salaire'] ?? $user->date_virement_salaire;
            $user->save();

             // Handle file upload if a new image is provided
            if ($request->hasFile('picture')) {
                 // Vérifier si l'utilisateur a déjà une photo et la supprimer
                if (!empty($user->picture) && Storage::disk('public')->exists($user->picture)) {
                    Storage::disk('public')->delete($user->picture);
                }
                $image = $request->file('picture');
                $imageName = $user->nom.'.'.time() . '.' . $image->getClientOriginalExtension();
                $path = $image->storeAs('avatars', $imageName, 'public');
                $user->picture = $path;  // Save file path
            }

            $user->save();
            return response()->json(['message' => 'Employee modifé avec succéss', 'user' => $user], 200);

         } catch (ValidationException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (QueryException $e) {
            Log::error('Database Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.Database error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            return response()->json(['error' => __('errors.An unexpected error occurred')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::FindOrFail($id);
            if($user->picture != 'assets/images/default_avatar.png'){
                Storage::disk('public')->delete($user->picture);
            }
            $user->delete();

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
