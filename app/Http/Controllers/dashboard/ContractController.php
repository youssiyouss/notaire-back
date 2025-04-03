<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $contracts = Contract::with('sub_categories')->get();

            return response()->json([
                'contracts' => $contracts,
           ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function search_users(Request $request)
    {
        try {

            $query = $request->input('search'); // Correct way to retrieve POST data

            $users = User::where('nom', 'LIKE', "%{$query}%")
                ->orWhere('prenom', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orWhere('date_de_naissance', 'LIKE', "%{$query}%")
                ->limit(10)
                ->get();
            return response()->json($users);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }

    }


    /**
     * Store a newly created resource in storage.
     */
    public function generateContract($templateId, $clientIds, $buyerIds, Request $request)
    {
        $template = ContractTemplate::findOrFail($templateId);
        $clients = Client::whereIn('id', $clientIds)->get();
        $buyers = Client::whereIn('id', $buyerIds)->get(); // Assuming buyers are also in the Client model

        // Determine transformation for Part A (Clients)
        $clientPronoun = $this->determinePronounCategory($clients, $template->pronoun_transformations);

        // Determine transformation for Part B (Buyers)
        $buyerPronoun = $this->determinePronounCategory($buyers, $template->pronoun_transformations);

        // Replace attribute placeholders
        $contractContent = $this->replaceAttributes($template->content, $request->attributeValues);

        // Replace pronoun placeholders for Clients (Part A)
        $contractContent = $this->replacePronouns($contractContent, 'A', $clientPronoun);

        // Replace pronoun placeholders for Buyers (Part B)
        $contractContent = $this->replacePronouns($contractContent, 'B', $buyerPronoun);

        // Save the contract
        $contract = Contract::create([
            'client_id' => json_encode($clientIds), // Store as JSON since multiple clients
            'template_id' => $template->id,
            'content' => $contractContent,
            'created_by' => auth()->id(),
        ]);

        // Generate PDF and return
        return $this->generatePdf($contractContent);
    }

    /**
     * Determines the correct pronoun transformation based on clients' gender.
     */
    private function determinePronounCategory($clients, $transformations)
    {
        if ($clients->count() === 1) {
            return $clients->first()->sex === 'male' ? $transformations['maleForm'] : $transformations['femaleForm'];
        }

        $hasMale = $clients->contains(fn($c) => $c->sex === 'male');
        $hasFemale = $clients->contains(fn($c) => $c->sex === 'female');

        if ($hasMale && !$hasFemale) {
            return $transformations['malepluralForm'];
        } elseif (!$hasMale && $hasFemale) {
            return $transformations['femalepluralForm'];
        } else {
            return $transformations['malepluralForm']; // Arabic grammar rule: masculine takes precedence
        }
    }

    /**
     * Replaces attributes placeholders in the contract content.
     */
    private function replaceAttributes($content, $clients)
    {
        foreach ($clients as $client) {
            foreach ($client->attributes as $attribute) {
                $content = str_replace("[" . $attribute->name . "]", $attribute->value, $content);
            }
        }
        return $content;
    }

    /**
     * Replaces pronoun placeholders for a given party (A or B).
     */
    private function replacePronouns($content, $party, $pronounValue)
    {
        return str_replace("{{$party}_pronoun}", $pronounValue, $content);
    }

    /**
     * Generates a PDF from the final contract content.
     */
    private function generatePdf($content)
    {
        $pdf = \PDF::loadHTML($content);
        return $pdf->download('contract.pdf');
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
        //
    }
}
