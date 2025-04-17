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
            $user = User::with('client','documents')->findOrFail($userId);

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
       // Log::info($request->all());
        // Retrieve the template and clients
        $template = ContractTemplate::find($request->contractType);
        $clients = User::whereIn('id', array_column($request->clients, 'id'))->get();
        $buyers = User::whereIn('id', array_column($request->buyers, 'id'))->get();

        // Create a new contract
        $contract = new Contract();
        $contract->template_id = $template->id;
        $contract->content = $this->generateContractContent(
            $template,
            $clients,
            $request['attributes'],
            $buyers,
            $request->notaryOffice
        );
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
                                font-family:  'Times New Roman','Arial Unicode MS', 'Traditional Arabic', sans-serif;
                                direction: rtl;
                                text-align: right;
                                font-size: 14px;
                            }

                            .ql-align-center { text-align: center; }
                            .ql-align-right { text-align: right; }
                            .ql-align-left { text-align: left; }

                            .ql-size-small { font-size: 0.75em; }
                            .ql-size-large { font-size: 1.5em; }
                            .ql-size-huge  { font-size: 2.5em; }
                        </style>
                    HTML;


            $fullHtml = <<<HTML
            <html lang="ar" dir="rtl">
            <head>
                $style
                <meta charset="UTF-8">
            </head>
            <body>
                <div style="display: flex; flex-direction: row-reverse;">
                    <!-- Contract content area -->
                    <div class="container" style="padding-right: 7.5cm; padding-bottom: 4cm; border-top: 2cm;">
                        <div style="border-right: 2px solid black;border-left: 2px solid black; text-align: left;">
                            $contract->content
                        </div>
                    </div>
                </div>
            </body>
            </html>
            HTML;

            // Generate PDF with wkhtmltopdf
            $pdf = PDF::loadHTML($fullHtml)
                ->setOption('encoding', 'utf-8')
                ->setOption('disable-smart-shrinking', true)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 40)
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




    protected function generateContractContent($template, $clients, $attributes, $buyers, $notaryOfficeId)
    {
        $content = $template->content;

        // 1. First replace notary office (simplest replacement)
        $content = $this->replaceNotaryOffice($content, $notaryOfficeId);

        // 2. Replace attributes between []
        $content = $this->replaceAttributes($content, $attributes);

        // 3. Then handle transformations (most complex)
        $content = $this->replacePronounTransformationsA(
            $content,
            json_decode($template->part_a_transformations, true),
            $clients
        );

        $content = $this->replacePronounTransformationsB(
            $content,
            json_decode($template->part_b_transformations, true),
            $buyers
        );

        $content = $this->replacePronounTransformations(
            $content,
            json_decode($template->part_all_transformations, true),
            $clients,
            $buyers
        );

        return $content;
    }
        protected function replaceAttributes($content, $attributes)
        {
            foreach ($attributes as $attribute) {
                $placeholder = '[' . $attribute['name'] . ']';
                $content = str_ireplace($placeholder, $attribute['value'], $content);
            }
            return $content;
        }

        protected function replacePronounTransformationsA($content, $transformations, $clients)
        {
            foreach ($transformations as $transformation) {
                $placeholder = '%!' . $transformation['placeholder'] . '!%';

                // Determine which form to use based on clients/buyers
                $form = $this->determinePronounForm($transformation, $clients);

                $content = str_replace($placeholder, $form, $content);
            }
            return $content;
        }

        protected function replacePronounTransformationsB($content, $transformations,$buyers)
        {
            foreach ($transformations as $transformation) {
                $placeholder = '%!!' . $transformation['placeholder'] . '!!%';

                // Determine which form to use based on clients/buyers
                $form = $this->determinePronounForm($transformation, $buyers);

                $content = str_replace($placeholder, $form, $content);
            }
            return $content;
        }

        protected function replacePronounTransformations($content, $transformations, $clients, $buyers)
        {
            foreach ($transformations as $transformation) {
                $placeholder = '%' . $transformation['placeholder'] . '%';

                // Determine which form to use based on clients/buyers
                $form = $this->determinePronounFormAll($transformation, $clients, $buyers);

                $content = str_replace($placeholder, $form, $content);
            }
            return $content;
        }

        protected function determinePronounForm($transformation, $users)
        {
            $maleCount = 0;
            $femaleCount = 0;

            foreach ($users as $user) {
                // Make case-insensitive comparison
                if (strtolower($user->sexe) === 'male') {
                    $maleCount++;
                } else {
                    $femaleCount++;
                }
            }

            if ($maleCount > 0 && $femaleCount === 0) {
                return $maleCount > 1 ? $transformation['malepluralForm'] : $transformation['maleForm'];
            } elseif ($femaleCount > 0 && $maleCount === 0) {
                return $femaleCount > 1 ? $transformation['femalepluralForm'] : $transformation['femaleForm'];
            } else {
                return $transformation['malepluralForm'];
            }
        }

        protected function determinePronounFormAll($transformation, $clients, $buyers)
        {
            // Count genders among all parties
            $maleCount = 0;
            $femaleCount = 0;

            foreach ($clients as $client) {
                $client->sexe === 'male' ? $maleCount++ : $femaleCount++;
            }

            foreach ($buyers as $buyer) {
                $buyer->sexe === 'male' ? $maleCount++ : $femaleCount++;
            }

            // Determine which form to use based on Arabic grammar rules
            if ($maleCount > 0 && $femaleCount === 0) {
                // All male
                return $maleCount > 1 ? $transformation['malepluralForm'] : $transformation['maleForm'];
            } elseif ($femaleCount > 0 && $maleCount === 0) {
                // All female
                return $femaleCount > 1 ? $transformation['femalepluralForm'] : $transformation['femaleForm'];
            } else {
                // Mixed group - in Arabic, masculine plural is used for mixed groups
                return $transformation['malepluralForm'];
            }
        }

    protected function replaceNotaryOffice($content, $notaryOfficeId)
    {
        $notary = User::find($notaryOfficeId);
        if ($notary) {
            $notaryName = $notary->nom . ' ' . $notary->prenom;
            // Replace both Arabic and French placeholders
            $content = str_replace(
                ['@موثق@', '@notaire@'],
                [$notaryName, $notaryName],
                $content
            );
        }
        return $content;
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
