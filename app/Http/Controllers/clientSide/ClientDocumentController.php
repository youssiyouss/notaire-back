<?php

namespace App\Http\Controllers\clientSide;

use App\Http\Controllers\Controller;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientDocumentController extends Controller
{
    /**
     * Get all documents for the authenticated client
     */
    public function index()
    {
        try {
            $user = Auth::guard('api')->user();
            
            // Get all documents for this user
            $documents = ClientDocument::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Define available document types
            $documentTypes = [
                ['id' => 1, 'name' => 'Carte d\'identité nationale (CIN)', 'type' => 'ID_CARD', 'required' => true],
                ['id' => 2, 'name' => 'Permis de conduire', 'type' => 'DRIVER_LICENSE', 'required' => false],
                ['id' => 3, 'name' => 'Passeport', 'type' => 'PASSPORT', 'required' => false],
                ['id' => 4, 'name' => 'Certificat de résidence', 'type' => 'RESIDENCE_CERTIFICATE', 'required' => false],
                ['id' => 5, 'name' => 'Acte de naissance', 'type' => 'BIRTH_CERTIFICATE', 'required' => false],
                ['id' => 6, 'name' => 'Contrat de vente', 'type' => 'SALES_CONTRACT', 'required' => false],
                ['id' => 7, 'name' => 'Contrat de garantie', 'type' => 'GUARANTEE_CONTRACT', 'required' => false],
                ['id' => 8, 'name' => 'Contrat d\'hypothèque', 'type' => 'MORTGAGE_CONTRACT', 'required' => false],
                ['id' => 9, 'name' => 'Contrat de donation', 'type' => 'DONATION_CONTRACT', 'required' => false],
                ['id' => 10, 'name' => 'Jugement judiciaire', 'type' => 'JUDICIAL_RULING', 'required' => false],
                ['id' => 11, 'name' => 'Décision administrative', 'type' => 'ADMINISTRATIVE_RULING', 'required' => false],
                ['id' => 12, 'name' => 'Certificat médical', 'type' => 'MEDICAL_CERTIFICATE', 'required' => false],
                ['id' => 13, 'name' => 'Contrat de reconnaissance de dette', 'type' => 'DEBT_RECOGNITION', 'required' => false],
            ];

            // Map documents to types
            $result = array_map(function($docType) use ($documents) {
                // Check for both English type code and French name for backward compatibility
                $uploadedDoc = $documents->first(function($doc) use ($docType) {
                    return $doc->document_type === $docType['type'] || 
                           $doc->document_type === $docType['name'];
                });
                
                return [
                    'id' => $docType['id'],
                    'name' => $docType['name'],
                    'type' => $docType['type'],
                    'required' => $docType['required'],
                    'uploaded' => $uploadedDoc ? true : false,
                    'file_path' => $uploadedDoc ? $uploadedDoc->image : null,
                    'uploaded_at' => $uploadedDoc ? $uploadedDoc->created_at : null,
                    'document_id' => $uploadedDoc ? $uploadedDoc->id : null,
                ];
            }, $documentTypes);

            return response()->json([
                'documents' => $result
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching client documents: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des documents'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload a document
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png,word|max:10240', // Max 10MB
                'document_type' => 'required|string',
            ]);

            $user = Auth::guard('api')->user();
            
            // Check if document already exists for this type
            $existingDoc = ClientDocument::where('user_id', $user->id)
                ->where('document_type', $request->document_type)
                ->first();

            if ($existingDoc) {
                // Delete old file
                if ($existingDoc->image && Storage::disk('public')->exists($existingDoc->image)) {
                    Storage::disk('public')->delete($existingDoc->image);
                }
            }

            // Store the new file
            $file = $request->file('file');
            $fileName = time() . '_' . $user->id . '_' . $request->document_type . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('client_documents', $fileName, 'public');

            // Create or update document record
            $document = ClientDocument::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'document_type' => $request->document_type
                ],
                [
                    'image' => $filePath,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            return response()->json([
                'message' => 'Document téléchargé avec succès',
                'document' => [
                    'id' => $document->id,
                    'file_path' => $filePath,
                    'uploaded_at' => $document->created_at,
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Fichier invalide',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error uploading document: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors du téléchargement du document'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a document
     */
    public function destroy($id)
    {
        try {
            $user = Auth::guard('api')->user();
            
            $document = ClientDocument::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Delete file from storage
            if ($document->image && Storage::disk('public')->exists($document->image)) {
                Storage::disk('public')->delete($document->image);
            }

            $document->delete();

            return response()->json([
                'message' => 'Document supprimé avec succès'
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Document non trouvé'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error deleting document: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors de la suppression du document'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download a document
     */
    public function download($id)
    {
        try {
            $user = Auth::guard('api')->user();
            
            $document = ClientDocument::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if (!$document->image || !Storage::disk('public')->exists($document->image)) {
                return response()->json([
                    'error' => 'Fichier non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            return Storage::disk('public')->download($document->image);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Document non trouvé'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error downloading document: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors du téléchargement du document'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
