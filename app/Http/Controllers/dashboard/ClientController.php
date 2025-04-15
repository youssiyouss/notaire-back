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
use Illuminate\Support\Facades\Http;
use Thiagoalessio\TesseractOCR\TesseractOCR;
use App\Models\Client;
use App\Models\User;
use App\Models\User_Document;
use Error;
use Exception;
use Illuminate\Support\Facades\DB;
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
        Log::info('Data:', ['data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'tel' => 'required|string|max:20',
            'adresse' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'sexe' => 'required|in:male,female',
            'date_de_naissance' => 'required|date',
            'role' => 'required|string',
            'ccp' => 'nullable|string',
            'salaire' => 'nullable|numeric',
            'date_virement_salaire' => 'nullable|date',
            'nationalite' => 'required|string|max:100',
            'lieu_de_naissance' => 'required|string|max:255',
            'nom_maternelle' => 'required|string|max:255',
            'prenom_mere' => 'required|string|max:255',
            'prenom_pere' => 'required|string|max:255',
            'emploi' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'document_type' => 'required|string|max:255',
            'id_document' => 'required|string|max:255',
            'date_emission_document' => 'required|date',
            'lieu_emission_document' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'tel' => $request->tel,
                'adresse' => $request->adresse,
                'password' => Hash::make($request->password),
                'sexe' => $request->sexe,
                'date_de_naissance' => $request->date_de_naissance,
                'role' => $request->role,
                'created_by' => auth()->id()
            ]);

            $client = Client::create([
                'user_id' => $user->id,
                'nationalite' => $request->nationalite,
                'lieu_de_naissance' => $request->lieu_de_naissance,
                'nom_maternelle' => $request->nom_maternelle,
                'prenom_mere' => $request->prenom_mere,
                'prenom_pere' => $request->prenom_pere,
                'emploi' => $request->emploi,
            ]);

            $document_update = User_Document::find($request->selectedDocumentId);

            if (!$document_update) {
                Log::error("No document found for user_id {$user->id} and document_type {$request->document_type}");
            }else{
                $document_update->user_id = $user->id;
                $document_update->id_document = $request->id_document;
                $document_update->date_emission_document= $request->date_emission_document;
                $document_update->lieu_emission_document= $request->lieu_emission_document;
                $document_update->save();
            }

            DB::commit();

            return response()->json(['message' => 'Client created successfully', 'user' => $user], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $user = Client::with(['parent.documents', 'parent.creator'])->findOrFail($id);
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
            'document_type' => 'required|in:carte_identite,permis,act_naissance,residance,passport',
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

        // Preprocess and crop image
        $croppedImages = $this->cropImage($fullImagePath);

        // Run OCR on cropped images
        $extractedData = [];
        foreach ($croppedImages as $key => $croppedPath) {
            $extractedData[$key] = (new TesseractOCR($croppedPath))
                ->lang('ara')
                ->run();
        }

        Log::info('Extracted Data:', $extractedData);

        // Validate if text was extracted
        if (empty(array_filter($extractedData))) {
            return response()->json(['message' => 'OCR failed to extract text'], 500);
        }

        // Apply OCR error correction
        foreach ($extractedData as $key => $value) {
            $extractedData[$key] = $this->correctOcrErrors($value);
        }

        // Save document
        $document = new User_Document();
        $document->user_id = auth()->id();
        $document->image = $imagePath;
        $document->document_type = $request->input('document_type');
        $document->id_document = $extractedData['id_number'] ?? '';
        $document->date_emission_document = $extractedData['card_issue_date'] ?? '';
        $document->lieu_emission_document = $extractedData['card_issue_place'] ?? '';
        $document->created_by = auth()->id();
        $document->save();

        return response()->json([
            'message' => 'Document enregistré avec succès',
            'data' => $extractedData,
            'document' => $document
        ], 200);
    }

    /**
     * Crops specific sections of the image for OCR
     */
   private function cropImage($imagePath)
    {
        $imagick = new \Imagick($imagePath);
        $width   = $imagick->getImageWidth();
        $height  = $imagick->getImageHeight();

        // Here we define one region: "bottom_right"
        // Adjust the percentages for x, y, w, h until the cropped area matches what you want
        $cropRegions = [
            // x, y, width, height (all in pixels, but derived from percentages)
            'bottom_right' => [
                0.3 * $width,  // Start cropping from 55% of the total width
                0.5 * $height, // Start cropping from 60% of the total height
                0.45 * $width,  // Crop 40% of the total width
                0.4 * $height, // Crop 40% of the total height
            ],
        ];

        $cropPaths = [];

        foreach ($cropRegions as $key => [$x, $y, $w, $h]) {
            // Clone original so each crop is from the full image
            $croppedImage = clone $imagick;
            $croppedImage->cropImage($w, $h, $x, $y);

            // Safety check in case the crop is out of bounds
            if ($croppedImage->getImageWidth() == 0 || $croppedImage->getImageHeight() == 0) {
                \Log::error("Cropping failed for $key - Empty Image.");
                continue;
            }

            // Write the cropped portion to disk
            $croppedPath = storage_path('app/public/documentations/cropped_' . $key . '_' . basename($imagePath));
            $croppedImage->writeImage($croppedPath);

            // Preprocess the just-cropped portion to enhance text
           // $processedPath = $this->preprocessImage($croppedPath);

            // Store the path to the preprocessed cropped image for OCR
            $cropPaths[$key] = $croppedPath;
        }

        return $cropPaths;
    }


    private function preprocessImage($imagePath)
    {
        $imagick = new \Imagick($imagePath);

        // 1. Optional: resize to a standard dimension to stabilize OCR
        //    (Preserve aspect ratio if needed: e.g. $imagick->resizeImage(1200, 0, \Imagick::FILTER_LANCZOS, 1))
        $imagick->resizeImage(1200, 800, \Imagick::FILTER_LANCZOS, 1);

        // 2. Auto-orient & deskew
        $imagick->autoOrient();
        $quantum = \Imagick::getQuantumRange();
        $imagick->deskewImage(0.4 * $quantum['quantumRangeLong']);

        // 3. Ensure ~300 DPI for small text
        $imagick->setImageResolution(300, 300);
        $imagick->resampleImage(300, 300, \Imagick::FILTER_LANCZOS, 1);

        // 4. Enhance color & contrast
        $imagick->modulateImage(110, 120, 100);            // brightness=110%, saturation=120%
        $imagick->sigmoidalContrastImage(true, 3, 50);     // midtone contrast
        $imagick->autoGammaImage();                        // auto gamma

        // 5. Denoise & sharpen
        $imagick->despeckleImage();
        $imagick->despeckleImage();
        $imagick->sharpenImage(0, 1);
        $imagick->unsharpMaskImage(2, 1, 1.2, 0.05);

        // 6. Optional: threshold to B&W if text is still faint
        $imagick->thresholdImage(128);

        // Save the final preprocessed image
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

    private function parseText(string $text, string $documentType): array
    {
        switch ($documentType) {
            case 'permis':
                return $this->parseDriversLicense($text);
            case 'carte_identite':
                return $this->parseNationalId($text);
            case 'act_naissance':
                return $this->parseActNaissance($text);
            default:
                throw new \Exception('Unsupported document type');
        }
    }

    private function parseDriversLicense(string $text): array
    {
        $idNumberPattern = '/رقم الرخصة:\s*([\d]+)/u';
        $idNumberPattern = '/رقم التعريف الوطني:\s*([\d]+)/u';
        $firstNamePattern = '/الإسم:\s*([\p{Arabic}\s]+)/u';
        $lastNamePattern = '/اللقب:\s*([\p{Arabic}\s]+)/u';
        $dobPattern = '/تاريخ و مكان الميلاد:\s*([\d]{2,4}[\s.*\-*\/]?[\d]{2}[\s.*\-*\/]?[\d]{2,4})/u';
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

    private function parseActNaissance(string $text): array
    {
        // Définition des expressions régulières
        $actNumberPattern = '/رقم الشهادة*?[:\s]+(\d+)/u';
        $fullNamePattern = '/المسمى(ة)[:\s]+([\p{Arabic}\s]+)/u';
        $dobPattern = '/في يوم[:\s]+([\d]{2,4}[\s.\-\/]*[\d]{2}[\s.\-\/]*[\d]{2,4})/u';
        $placeOfBirthPattern = '/ولد(ت) ب [:\s]+([\p{Arabic}\s]+)/u';
        $fatherNamePattern = '/ابن(ة)[:\s]+([\p{Arabic}\s]+)/u';
        $motherNamePattern = '/و[:\s]+([\p{Arabic}\s]+)/u';
        $issueDatePattern = '/في[:\s]+([\d]{2,4}[\s.\-\/]*[\d]{2}[\s.\-\/]*[\d]{2,4})/u';
        $issuePlacePattern = '/حررت ب[:\s]+([\p{Arabic}\s]+)/u';
        $sexPattern = '/الجنس:\s*([\p{Arabic}]+)/u';

        // Extraction des données
        preg_match($actNumberPattern, $text, $actNumberMatches);
        preg_match($fullNamePattern, $text, $fullNameMatches);
        preg_match($dobPattern, $text, $dobMatches);
        preg_match($placeOfBirthPattern, $text, $placeMatches);
        preg_match($fatherNamePattern, $text, $fatherMatches);
        preg_match($motherNamePattern, $text, $motherMatches);
        preg_match($issuePlacePattern, $text, $issuePlaceMatches);
        preg_match($issueDatePattern, $text, $issueDateMatches);
        preg_match($sexPattern, $text, $sexMatches);

        return [
            'act_number' => $actNumberMatches[1] ?? null,
            'full_name' => trim($fullNameMatches[1] ?? ''),
            'date_of_birth' => $dobMatches[1] ?? null,
            'place_of_birth' => trim($placeMatches[1] ?? ''),
            'father_name' => trim($fatherMatches[1] ?? ''),
            'mother_name' => trim($motherMatches[1] ?? ''),
            'card_issue_place' => trim($issuePlaceMatches[1] ?? ''),
            'card_issue_date' => $issueDateMatches[1] ?? null,
            'sex' => $sexMatches[1] ?? null,

        ];
    }

    public function extractText(Request $request)
    {
        $response = Http::attach(
            'image', file_get_contents($request->file('image')->path()), 'image.jpg'
        )->timeout(120)->post('http://127.0.0.1:5000/extract_text');
        Log::info('OCR Response:', $response->json());
        return response()->json($response->json());
    }
}
