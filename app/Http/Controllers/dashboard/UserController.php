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
            if (auth()->check()) {
                $clients = User::where('role','client')->get();
                $members = User::where('role','admin')->get();

                return response()->json([
                    'clients' => $clients,
                    'members' => $members,
                ], 200);
             } else {
                return response()->json(['message' => 'Nope <3'], 401);
            }



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
            $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'tel' => 'required|regex:/^\+?[0-9]\d{0,14}$/|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'sexe'=>'required|',
                'date_de_naissance'=>'required|date',
                'role'=>'required|string',
                'ccp'=>'nullable|string',
                'date_virement_salaire'=>'required|date',
                'picture' => 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000'
            ]);

            // Create the user with basic information
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'tel' => $request->tel,
                'password' => Hash::make($request->password),
                'role' => 'employee',
                'sexe'=>$request->sexe,
                'date_de_naissance'=>$request->date_de_naissance,
                'ccp'=>$request->ccp,
                'date_virement_salaire'=>$request->date_virement_salaire
            ]);

            if($request->hasfile('picture')){
                $picName = $request->nom.'.'.time().'.'.$request->file('picture')->getClientOriginalExtension();
                $user->picture = $request->file('picture')->storeAs('avatars',$picName, 'public');
            }
            // Return success response
            return response()->json(['message' => 'Employee registered successfully'], 201);
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
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $id,
            'tel' => 'nullable|regex:/^\+?[0-9]\d{0,14}$/|max:20|unique:users,tel,' . $id, // Allow updating the phone number for the specific user
            'password' => 'required|string|min:8|confirmed',
            'sexe'=>'required|',
            'date_de_naissance'=>'required|date',
            'role'=>'required|string',
            'ccp'=>'nullable|string',
            'date_virement_salaire'=>'required|date',
            'picture'=> 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:2000',

        ]);

        try {
            $user = User::findOrFail($id);
            $user->update(array_filter($validated));  // Only update fields that are not null
            if($request->hasfile('picture')){
                if($user->picture != 'assets/images/default_avatar.png'){
                    Storage::delete($user->picture);
                }
                $picName = $request->name.'.'.time().'.'.$request->file('picture')->getClientOriginalExtension();
                $user->picture = $request->file('picture')->storeAs('avatars',$picName, 'public');
            }
            return response()->json(['message' => 'Employee modifé avec succéss', 'user' => $user], 200);
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
