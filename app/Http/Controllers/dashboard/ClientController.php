<?php

namespace App\Http\Controllers\dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use Thiagoalessio\TesseractOCR\TesseractOCR;
use App\Models\Client;
use App\Models\User_Document;
use Error;
use Exception;
require_once base_path('vendor/thiagoalessio/tesseract_ocr/src/TesseractOCR.php');
use Imagick;


class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $clients = Client::with('parent.creator')->paginate(20);

            return response()->json([
                'clients' => $clients,
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = Client::with('parent.creator')->findOrFail($id);
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
        try {
            $user = Client::FindOrFail($id);
            if($user->parent->picture){
                Storage::disk('public')->delete($user->parent->picture);
            }
            $user->parent->delete();
            $user->delete();
            return response()->json(['message' => 'Client deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    //******************************************//

    public function process_image(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'document_type' => 'required|in:carte_identite,permis',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['message' => 'No image uploaded'], 400);
        }

        $picName = time() . '.' . $request->file('image')->getClientOriginalExtension();
        $imagePath = $request->file('image')->storeAs('documentations/'.$request->document_type.'/', $picName, 'public');

        if (!Storage::disk('public')->exists('documentations/'.$request->document_type.'/'. $picName)) {
            return response()->json(['message' => 'File could not be saved'], 500);
        }

        $fullImagePath = Storage::disk('public')->path($imagePath);

        // Preprocess Image
        $preprocessedPath = $this->preprocessImage($fullImagePath);

        // Extract Text
        $text = (new TesseractOCR($fullImagePath))
            ->lang('ara')
            ->run();

        Log::info('Extracted Text:', ['text' => $text]);

        // Debug response to check OCR output
        if (empty(trim($text))) {
            return response()->json(['message' => 'OCR failed to extract text'], 500);
        }

        // Apply corrections
        $text = $this->correctOcrErrors($text);

        // Parse extracted text
        $documentType = $request->input('document_type');
        $extractedData = $this->parseText($text, $documentType);

         // Enregistrer le document en base de données
        $document = new User_Document();
        $document->user_id = auth()->id();
        $document->image = $imagePath;
        $document->document_type = $documentType;
        $document->created_by = auth()->id();
        $document->save();

        return response()->json([
            'message' => 'Document enregistré avec succès',
            'extracted_text' => $text, // Include for debugging
            'data' => $extractedData,
            'document' => $document
        ], 200);
    }


    private function preprocessImage($imagePath)
    {
        $imagick = new Imagick($imagePath);

        // Convert to grayscale
        $imagick->setImageColorspace(Imagick::COLORSPACE_GRAY);

        // Save the preprocessed image
        //Storage::disk('public')->path($imagePath);
        $preprocessedPath = storage_path('app/public/documentations/preprocessed_' . basename($imagePath));
        $imagick->writeImage($preprocessedPath);

        return $preprocessedPath;
    }

    private function correctOcrErrors(string $text): string
    {
        $corrections = [
            'أنثن' => 'أنثى',
            'ذكذ' => 'ذكر',
            'بندية' => 'بلدية',
        ];

        return str_replace(array_keys($corrections), array_values($corrections), $text);
    }

    private function detectDocumentType(string $text): string
    {
        if (strpos($text, 'permis') !== false) {
            return 'drivers_license';
        } elseif (strpos($text, 'carte_identite') !== false) {
            return 'national_id';
        } else {
            throw new \Exception('Unknown document type');
        }
    }


    private function parseText(string $text, string $documentType): array
    {
        switch ($documentType) {
            case 'permis':
                return $this->parseDriversLicense($text);
            case 'carte_identite':
                return $this->parseNationalId($text);
            default:
                throw new \Exception('Unsupported document type');
        }
    }


    private function parseDriversLicense(string $text): array
    {
        // Define regex patterns for driver's license
        $firstNamePattern = '/First Name:\s*(\w+)/i';
        $lastNamePattern = '/Last Name:\s*(\w+)/i';
        $dobPattern = '/Date of Birth:\s*(\d{2}\/\d{2}\/\d{4})/i';

        preg_match($firstNamePattern, $text, $firstNameMatches);
        preg_match($lastNamePattern, $text, $lastNameMatches);
        preg_match($dobPattern, $text, $dobMatches);

        return [
            'first_name' => $firstNameMatches[1] ?? null,
            'last_name' => $lastNameMatches[1] ?? null,
            'date_of_birth' => $dobMatches[1] ?? null,
        ];
    }

    private function parseNationalId(string $text): array
    {
        // Define regex patterns
        $idNumberPattern = '/رقم التعريف الوطني:\s*([\d]+)/u';
        $firstNamePattern = '/الإسم:\s*([\p{Arabic}\s]+)/u';
        $lastNamePattern = '/اللقب:\s*([\p{Arabic}\s]+)/u';
       // $dobPattern = '/تاريخ الميلاد:\s*([\d]{2,4}[.\-\/\s]?[\d]{2}[.\-\/\s]?[\d]{2,4})/u';
        $dobPattern = '/تاريخ الميلاد:\s*([\d]{2,4}[\s.*\-*\/]?[\d]{2}[\s.*\-*\/]?[\d]{2,4})/u';

        $sexPattern = '/الجنس:\s*([\p{Arabic}]+)/u';
        $placeOfBirthPattern = '/مكان الميلاد:\s*([\p{Arabic}\s]+)/u';
        $issuePlacePattern = '/سلطة الإصدار:\s*([\p{Arabic}\- ]+)/u';
        $issueDatePattern = '/تاريخ الإصدار:\s*([\d]{4}[.\-\/][\d]{2}[.\-\/][\d]{2})/u';

        // Match patterns
        preg_match($idNumberPattern, $text, $idMatches);
        preg_match($firstNamePattern, $text, $firstNameMatches);
        preg_match($lastNamePattern, $text, $lastNameMatches);
        preg_match($dobPattern, $text, $dobMatches);
        preg_match($sexPattern, $text, $sexMatches);
        preg_match($placeOfBirthPattern, $text, $placeMatches);
        preg_match($issuePlacePattern, $text, $issuePlaceMatches);
        preg_match($issueDatePattern, $text, $issueDateMatches);

        return [
            'id_number' => $idMatches[1] ?? null,
            'first_name' => trim($firstNameMatches[1] ?? ''),
            'last_name' => trim($lastNameMatches[1] ?? ''),
            'date_of_birth' => $dobMatches[1] ?? null,
            'sex' => $sexMatches[1] ?? null,
            'place_of_birth' => trim($placeMatches[1] ?? ''),
            'card_issue_place' => trim($issuePlaceMatches[1] ?? ''),
            'card_issue_date' => $issueDateMatches[1] ?? null,
        ];
    }


    public function processId(Request $request)
    {
        // Validate the uploaded image
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);
        $langs=[];
        foreach((new TesseractOCR())->availableLanguages() as $lang)   $langs[]= $lang;
        dd($lang);

        // Save the uploaded image
        $imagePath = $request->file('image')->store('uploads');

        // Use Tesseract OCR to extract text
        $text = (new TesseractOCR(storage_path('app/'.$imagePath)))
            ->lang('ara') // Set the language
            ->run();

        // Parse the extracted text
        $extractedData = $this->parseText($text);

        return response()->json($extractedData);
    }
}
