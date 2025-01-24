<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate the profile data
        $request->validate([
            'nationalite' => 'nullable|string|max:100',
            'lieu_de_naissance' => 'nullable|string|max:255',
            'nom_maternelle' => 'nullable|string|max:255',
            'prenom_mere' => 'nullable|string|max:255',
            'prenom_pere' => 'nullable|string|max:255',
            'numero_acte_naissance' => 'nullable|string|max:255',
            'type_carte' => 'nullable|string|max:255',
            'date_emission_carte' => 'nullable|string|max:255',
            'lieu_emission_carte' => 'nullable|string|max:255',
            'emploi' => 'nullable|string|max:255',
        ]);

        // Get the authenticated user's client profile
        $client = $request->user()->client;

        // Update the client profile with additional information
        $client->update([
            'nationalite' => $request->nationalite,
            'lieu_de_naissance' => $request->lieu_de_naissance,
            'nom_maternelle' => $request->nom_maternelle,
            'prenom_mere' => $request->prenom_mere,
            'prenom_pere' => $request->prenom_pere,
            'numero_acte_naissance' => $request->numero_acte_naissance,
            'type_carte' => $request->type_carte,
            'date_emission_carte' => $request->date_emission_carte,
            'lieu_emission_carte' => $request->lieu_emission_carte,
            'emploi' => $request->emploi,
        ]);

        // Return success response
        return response()->json(['message' => 'Client profile updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
