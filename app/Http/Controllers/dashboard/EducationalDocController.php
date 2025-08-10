<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEducationalDocRequest;
use App\Http\Requests\UpdateEducationalDocRequest;
use App\Models\User;
use App\Models\EducationalDocs;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewEducationalAssetNotification;
use App\Events\NewEducationAsset;


class EducationalDocController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $docs = EducationalDocs::with('creator','editor')->paginate(15);

            return response()->json([
                'docs' => $docs, 
            ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    } 

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEducationalDocRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // Ajouter le user connecté comme créateur
            $data['created_by'] = Auth::id(); // ou Auth::user()->id

            // Gestion du fichier
            if ($request->hasFile('file')) {
                $picName = time() . '_' . $request->file('file')->getClientOriginalName();
                $path = $request->file('file')->storeAs('educational_pdfs', $picName, 'public');

                if (Storage::disk('public')->exists($path)) {
                    $data['file_path'] = $path;
                } else {
                    return response()->json(['message' => 'Le fichier pdf n’a pas pu être sauvegardé.'], 500);
                }
            }

            // Création de l'entreprise
            $content = EducationalDocs::create($data);
            
            //send notifiactions
                $message = [
                    'key' => $content->title,
                    'params' => ['doc_title' => $content->title],
                ];
                $link = 'docs-educationnel/' . $content->id;
                if($content->audience == "Employé"){
                    $notifiables = User::where('role', '!=', 'Client')->get();
                }
                else if($content->audience == "Client"){
                    $notifiables = User::where('role', 'Client')->get();
                }else{
                    $notifiables = User::all();
                }
                Notification::send($notifiables, new NewEducationalAssetNotification($message, $link, 'notif.new_edu_doc_added'));
                broadcast(new NewEducationAsset($notifiables))->toOthers();

            DB::commit();

            return response()->json([
                'message' => 'Document éducatif créé avec succès.',
                'content' => $content,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de la création du document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du document.',
            ], 500);
        }
        
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $doc = EducationalDocs::with('creator','editor')->findOrFail($id);

            return response()->json($doc, 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEducationalDocRequest $request, string $id)
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $doc = EducationalDocs::findOrFail($id);

            // Ajouter l'utilisateur connecté comme modificateur
            $data['updated_by'] = Auth::id();

            // Gestion du fichier
            if ($request->hasFile('file_path')) {
                // Supprimer l'ancien fichier s'il existe
                if (!empty($doc->file_path) && Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                }

                $picName = time() . '_' . $request->file('file_path')->getClientOriginalName();
                $path = $request->file('file_path')->storeAs('educational_pdfs', $picName, 'public');

                if (Storage::disk('public')->exists($path)) {
                    $data['file_path'] = $path;
                } else {
                    return response()->json(['message' => 'Le fichier PDF n’a pas pu être sauvegardé.'], 500);
                }
            }

            // Mise à jour du document
            $doc->update($data);

            DB::commit();

            return response()->json([
                'message' => 'Document éducatif mis à jour avec succès.',
                'document' => $doc,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de la mise à jour du document éducatif', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour du document éducatif.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $Doc = EducationalDocs::FindOrFail($id);
            $Doc->delete();

            return response()->json(['message' => 'Employee deleted successfully'], 201);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function trash()
    {
        $trashed = EducationalDocs::onlyTrashed()->get();
        return response()->json($trashed);
    }

    public function restore($id)
    {
        $document = EducationalDocs::withTrashed()->findOrFail($id);
        $document->restore();

        return response()->json([
            'message' => 'Document restored successfully'
        ]);
    }

    public function forceDelete($id)
    {
        $document = EducationalDocs::withTrashed()->findOrFail($id);
        Storage::disk('public')->delete($document->file_path);
        $document->forceDelete();

        return response()->json([
            'message' => 'Document permanently deleted'
        ]);
    }


}
