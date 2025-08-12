<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEducationalVideoRequest;
use App\Http\Requests\UpdateEducationalVideoRequest;
use App\Models\EducationalVideo;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewEducationalAssetNotification;
use App\Events\NewEducationAsset;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EducationalVideoController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $videos = EducationalVideo::with('creator','editor')->latest()->paginate(15);
            return response()->json(['videos' => $videos]);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEducationalVideoRequest $request)
    {
        $this->authorize('create', EducationalVideo::class);

        DB::beginTransaction();
        try{
            $data = $request->validated();
            $video = new EducationalVideo();
            $video->title = $data['title'] ?? null;
            $video->description = $data['description'] ?? null;
            $video->category = $data['category'] ?? null;
            $video->audience = $data['audience'] ?? null;
            $video->duration = $data['duration'] ?? null;
            $video->source = $data['source'];

            if ($data['source'] === 'Youtube') {
                $video->video_url = $data['video_url'];
                $video->thumbnail = $this->youtubeThumbnail($data['video_url']);
            } else {
                // fichier local
                $file = $request->file('video_path'); 
                $name =  time() . '_' . $file->getClientOriginalName();
                $video->video_path = $file->storeAs('educational_videos', $name, 'public'); 
                // durée & miniatures : idéalement via job avec FFmpeg

                // Handle file upload if a new image is provided
                if ($request->hasFile('thumbnail')) {
                    $image = $request->file('thumbnail'); 
                    $imageName = time() . '_' . $image->getClientOriginalName();
                    $path = $image->storeAs('video_thumbnails', $imageName, 'public');
                    $video->thumbnail = $path;  // Save file path
                }
            }

            $video->created_by = Auth::id();
            $video->save();
            //send notifiactions
                $message = "";
                $link = "";
                $notifiables = [];

                $message = [
                    'key' => $video->title,
                    'params' => ['video_title' => $video->title],
                ];
                $link = 'videos-educationnel/' . $video->id;
                if($video->audience == "Employé"){
                    $notifiables = User::where('role', '!=', 'Client')->get();
                }
                else if($video->audience == "Client"){
                    $notifiables = User::where('role', 'Client')->get();
                }else{
                    $notifiables = User::all();
                }
                Notification::send($notifiables, new NewEducationalAssetNotification($message, $link, 'notif.new_edu_video_added'));
                broadcast(new NewEducationAsset($notifiables))->toOthers();

            DB::commit();           
            return response()->json(['message' => 'Vidéo Enregistré avec succés', 'video' => $video], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors de la création de la vidéo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création de la vidéo.',
            ], 500);
        }
        

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $doc = EducationalVideo::with('creator','editor')->findOrFail($id);

            return response()->json($doc, 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEducationalVideoRequest $request, string $id)
    {
        
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            $video = EducationalVideo::findOrFail($id);
            $this->authorize('update', $video);

            // Basic fields
            $video->title       = $validated['title'] ?? $video->title;
            $video->description = $validated['description'] ?? $video->description;
            $video->audience    = $validated['audience'] ?? $video->audience;
            $video->category    = $validated['category'] ?? $video->category;
            $video->duration    = isset($validated['duration']) ? (int) $validated['duration'] : $video->duration;
            $video->source      = $validated['source'] ?? $video->source;
            $video->video_url   = $validated['video_url'] ?? $video->video_url;
            $video->updated_by = Auth::id();

            // Handle video upload
            if ($request->hasFile('video_path') && $validated['source'] == 'Ordinateur') {
                if (!empty($video->video_path) && Storage::disk('public')->exists($video->video_path)) {
                    Storage::disk('public')->delete($video->video_path);
                }
                $file = $request->file('video_path'); 
                $name =  time() . '_' . $file->getClientOriginalName();
                $video->video_path = $file->storeAs('educational_videos', $name, 'public'); 
                $video->video_url = null; 
                $video->thumbnail = null; 
            }
            else if($request->video_url && $validated['source'] == 'Youtube'){
                $video->thumbnail = $this->youtubeThumbnail($validated['video_url']);
                if (!empty($video->video_path) && Storage::disk('public')->exists($video->video_path)) {
                    Storage::disk('public')->delete($video->video_path);
                }
                $video->video_path = null;  
            }
            if($request->hasFile('thumbnail')){
                if (!empty($video->thumbnail) && Storage::disk('public')->exists($video->thumbnail)) {
                    Storage::disk('public')->delete($video->thumbnail);
                }
                $image = $request->file('thumbnail');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $path = $image->storeAs('video_thumbnails', $imageName, 'public');
                $video->thumbnail = $path;  // Save file path
            }

            $video->save();
            DB::commit();

            return response()->json([
                'message' => 'Video updated successfully',
                'data'    => $video
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update video',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $video = EducationalVideo::findOrFail($id);
        $this->authorize('delete', $video);
        $video->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function trash()
    {
        $trashed = EducationalVideo::onlyTrashed()->get();
        return response()->json($trashed);
    }

    public function restore($id)
    {
        $video = EducationalVideo::withTrashed()->findOrFail($id);
        $video->restore();

        return response()->json([
            'message' => 'Document restored successfully'
        ]);
    }

    public function forceDelete(string $id)
    {
        $video = EducationalVideo::withTrashed()->findOrFail($id);
        if (!empty($video->video_path) && Storage::disk('public')->exists($video->video_path)) {
            Storage::disk('public')->delete($video->video_path);
        }
        $video->forceDelete();

        return response()->json([
            'message' => 'Document permanently deleted'
        ]);
    }

    private function youtubeThumbnail(string $url)
    {
        // extract video id (simple)
        preg_match('/v=([A-Za-z0-9_\-]+)/', $url, $matches);
        $id = $matches[1] ?? null;
        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : null;
    }
}
