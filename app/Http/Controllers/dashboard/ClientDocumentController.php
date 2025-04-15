<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\ClientDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ClientDocumentController extends Controller
{


    public function index()
    {
        // Récupérer tous les clients (users) ayant au moins un document
        $clients = User::whereHas('documents')->with([
                                                        'documents',
                                                        'client',
                                                    ]) ->paginate(30);

        return response()->json([
            'status' => true,
            'docs' => $clients
        ]);
    }


    public function store(Request $request)
    {
        // Vérifier si le client existe
        $client = User::findOrFail($request->client_id);

        // Valider les données
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|max:255',
            'id_document' => 'required|string|max:255',
            'date_emission_document' => 'required|date',
            'lieu_emission_document' => 'required|string|max:255',
            'document_image.*' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Création du document
        $doc = new ClientDocument();
        $doc->user_id = $client->id;
        $doc->document_type = $request->document_type;
        $doc->id_document = $request->id_document;
        $doc->date_emission_document = $request->date_emission_document;
        $doc->lieu_emission_document = $request->lieu_emission_document;
        $doc->created_by = auth()->id();

        // Sauvegarde des fichiers
        if ($request->hasFile('document_image')) {
            $fieName = $request->document_type . '.' . time() . '.' . $request->file('document_image')->getClientOriginalExtension();
            $path = $request->file('document_image')->storeAs('user_documents/'.$doc->user_id, $fieName, 'public');

            // Check if the file is saved
            if (Storage::disk('public')->exists('user_documents/'.$doc->user_id.'/' . $fieName)) {
                $doc->image = $path;
                $doc->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Document enregistré avec succès.'
                ]);
            } else {
                return response()->json(['message' => 'File could not be saved'], 500);
            }
         }
    }

    public function destroy(string $id)
    {
        try {
            $doc = ClientDocument::FindOrFail($id);
            Storage::disk('public')->delete($doc->image);
            $doc->delete();

            return response()->json(['message' => 'Document deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
