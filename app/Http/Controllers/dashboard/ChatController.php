<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\NewEducationalAssetNotification;
use App\Events\ChatMessage;
use App\Events\MessageRead;
use App\Events\MessageDeleted;

class ChatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($userId)
    {
        try {
            $messages = Chat::where(function ($q) use ($userId) {
                            $q->where([['sender_id', auth()->id()], ['receiver_id', $userId]])
                            ->where('deleted_by_sender', false);
                        })
            ->orWhere(function ($q) use ($userId) {
                            $q->where([['sender_id', $userId], ['receiver_id', auth()->id()]])
                            ->where('deleted_by_receiver', false);
                        })
            ->orderBy('id', 'desc')
            ->paginate(20); // 20 messages per page, descending

            return response()->json($messages);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        try{
            $message = Chat::create([
                'sender_id' => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'content' => $request->content,
            ]);

            $message->load('sender'); // assuming relation exists
            broadcast(new ChatMessage($message, $request->receiver_id))->toOthers();

            return response()->json($message, 201);
        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function markAsRead($userId)
    {

        Chat::where('receiver_id', auth()->id())
            ->where('sender_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
               // 'read_at' => now(),
            ]);

        broadcast(new MessageRead(auth()->id(), $userId))->toOthers();

        return response()->json(['status' => 'success']);
    }


    public function unreadCount(Request $request)
    {
        $userId = auth()->id();

        $count = Chat::where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->count();

        return response()->json(['unread' => $count]);
    }


    public function deleteForMe($id)
    {
        try{

            $chat = Chat::findOrFail($id);

            if ($chat->sender_id === auth()->id()) {
                $chat->deleted_by_sender = true;
            } elseif ($chat->receiver_id === auth()->id()) {
                $chat->deleted_by_receiver = true;
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $chat->save();

            return response()->json(['message' => 'Message supprimé pour vous uniquement']);
        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

    }

    public function deleteForEveryone($id)
    {
        try{
            $chat = Chat::findOrFail($id);

            // Only sender can "retirer"
            if ($chat->sender_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // delete file if attached
            if ($chat->file_url && $chat->type !== "text") {
                Storage::disk('public')->delete($chat->file_url);
            }

            $chat->delete();
            // event(new MessageDeleted($chat->id, $chat->receiver_id));
            broadcast(new MessageDeleted($chat->id, $chat->receiver_id, $chat->sender_id))->toOthers();


            return response()->json(['message' => 'Message retiré pour tout le monde']);

        }catch (\Error $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch(\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }


    public function upload(Request $request)
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'file' => 'required|file|max:20480', // 20MB
                'receiver_id' => 'required|exists:users,id',
                'type' => 'required|in:image,video,document'
            ]);

            $chat = Chat::create([
                'sender_id'   => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'content'     => $request->file('file')->getClientOriginalName(),
                'type'        => $request->type,
            ]);
                if ($request->hasFile('file')) {
                    $file = $request->file('file')->getClientOriginalName();
                    $path = $request->file('file')->storeAs('chats/'. auth()->id().'/'.$request->type, $file, 'public');

                    if (Storage::disk('public')->exists($path)) {
                        $chat->file_url = $path;
                        $chat->save();
                    } else {
                        return response()->json(['message' => 'Le fichier pdf n’a pas pu être sauvegardé.'], 500);
                    }
                }

            $chat->load('sender'); // assuming relation exists
            broadcast(new ChatMessage($chat, $request->receiver_id))->toOthers();

            DB::commit();

            return response()->json([
                'message' => 'Fichier enregistrer avec succès.',
                'chat' => $chat,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du document.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function download($id)
    {
        $chat = Chat::findOrFail($id);

        if ($chat->sender_id !== auth()->id() && $chat->receiver_id !== auth()->id()) {
            abort(403, 'Accès non autorisé');
        }

        if (!$chat->file_url || !Storage::disk('public')->exists($chat->file_url)) {
            abort(404, 'Fichier introuvable');
        }

        return Storage::disk('public')->download($chat->file_url, $chat->content);
    }

    public function forward(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:chats,id',
            'receiver_id' => 'required|exists:users,id',
        ]);

        $original = Chat::findOrFail($request->message_id);

        $forwarded = Chat::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $request->receiver_id, // ✅ change here
            'content'     => $original->content,
            'type'        => $original->type,
            'file_url'    => $original->file_url,  // ✅ reuse file path
        ]);

        $forwarded->load('sender');
        broadcast(new ChatMessage($forwarded, $request->receiver_id))->toOthers();

        return response()->json(['chat' => $forwarded], 201);
    }


}
