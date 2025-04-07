<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractAttributes;
use App\Models\Client;
use App\Models\User;
use App\Models\ContractClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $contracts = Contract::all();

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

    public function getClientDetails($userId)
    {
        try {
            $user = User::with('client')->findOrFail($userId);

            // Merge client data directly into user object
            $userData = $user->toArray();
            if ($user->client) {
                $userData['client'] = $user->client->toArray();
            }

            return response()->json($userData);

        } catch (\Exception $e) {
            Log::error('Error fetching client details: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Retrieve the template and clients
        $template = ContractTemplate::find($request->contractType);
        $clients = User::whereIn('id', array_column($request->clients, 'id'))->get();
        $buyers = User::whereIn('id', array_column($request->buyers, 'id'))->get();

        // Create a new contract
        $contract = new Contract();
        $contract->template_id = $template->id;
        $contract->content = $this->generateContractContent($template, $clients, $request->attributes);
        $contract->created_by = Auth::id();
        $contract->save();

         // Store contract attributes
        if ($request->attributes) {
            foreach ($request->attributes as $attributeData) {
                $attribute = new ContractAttributes();
                $attribute->contract_id = $contract->id;
                $attribute->name = $attributeData['name'];
                $attribute->value = $attributeData['value'];
                $attribute->save();
            }
        }

        // Attach clients to the contract
        foreach ($clients as $client) {
            $c = new ContractClient();
            $c->client_state =  $request->clientType;
            $c->contract_id = $contract->id;
            $c->client_id = $client->id;
            $c->save();
        }
        foreach ($buyers as $client) {
            $c = new ContractClient();
            $c->client_state =  $request->buyerType;
            $c->contract_id = $contract->id;
            $c->client_id = $client->id;
            $c->save();
        }
        $style = <<<'HTML'
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body {
                    font-family: 'Arial Unicode MS', 'Traditional Arabic', 'Times New Roman', sans-serif;
                    direction: rtl;
                    text-align: right;
                }
            </style>
            HTML;

            $fullHtml = '<html dir="rtl" lang="ar"><head>' . $style . '</head><body>' . $contract->content . '</body></html>';

            // Generate PDF with wkhtmltopdf
            $pdf = PDF::loadHTML($fullHtml)
                ->setOption('encoding', 'utf-8')
                ->setOption('disable-smart-shrinking', true)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 10)
                ->setOption('margin-right', 10)
                ->setOption('no-outline', true)
                ->setOption('enable-local-file-access', true); // Important for local fonts

            $pdfContent = $pdf->output();
            $fileName = 'contracts/contract_' . $contract->id . '_' . time() . '.pdf';

            Storage::disk('public')->makeDirectory('contracts');
            Storage::disk('public')->put($fileName, $pdfContent);

            $contract->pdf_path = $fileName;
            $contract->save();

            return response()->json([
                'message' => 'Contract created successfully!',
                'contract' => $contract,
                'pdfUrl' => Storage::disk('public')->url($fileName) // Add this line
            ], 201);

        }


    /**
     * Generate the contract content by replacing attributes and transformations.
     */
    private function generateContractContent($template, $clients, $attributes)
    {
        $content = $template->content;

        // Replace attributes in content
        foreach ($attributes as $attributeData) {
            $content = str_replace('[' . $attributeData['name'] . ']', $attributeData['value'], $content);
        }

        // Replace transformations based on clients' sex and number
        $content = $this->replacePronounTransformations($content, $clients);

        return $content;
    }

    /**
     * Replace pronoun transformations in the contract template content.
     */
    private function replacePronounTransformations($content, $clients)
    {
        // Assuming we need to check if there is at least one female in the group
        $isFemalePresent = $clients->contains(function ($client) {
            return $client->sexe == 'female';
        });

        // Determine the transformation based on gender and number
        $transformation = $isFemalePresent ? 'femalepluralForm' : 'malepluralForm'; // For now we assume plural forms as an example

        // Replace pronoun placeholders with the corresponding transformations
        $content = str_replace('{{transformation}}', $transformation, $content);

        return $content;
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
