<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
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


    public function getUsersList($userId)
    {
        try {
            // Subquery: last message content (excluding deleted ones)
            $lastMessageSub = Chat::select('content')
                ->where(function($q) use ($userId) {
                    $q->whereColumn('chats.sender_id', 'users.id')
                        ->where('chats.receiver_id', $userId)
                        ->where('deleted_by_receiver', false);
                })
                ->orWhere(function($q) use ($userId) {
                    $q->whereColumn('chats.receiver_id', 'users.id')
                        ->where('chats.sender_id', $userId)
                        ->where('deleted_by_sender', false);
                })
                ->orderByDesc('chats.id')
                ->limit(1);

            // Subquery: last message date (excluding deleted ones)
            $lastMessageAtSub = Chat::select('created_at')
                ->where(function($q) use ($userId) {
                    $q->whereColumn('chats.sender_id', 'users.id')
                        ->where('chats.receiver_id', $userId)
                        ->where('deleted_by_receiver', false);
                })
                ->orWhere(function($q) use ($userId) {
                    $q->whereColumn('chats.receiver_id', 'users.id')
                        ->where('chats.sender_id', $userId)
                        ->where('deleted_by_sender', false);
                })
                ->orderByDesc('chats.id')
                ->limit(1);

            // Users with active chats (exclude fully deleted conversations)
            $chats = User::where('id', '!=', $userId)
                ->where(function($q) use ($userId) {
                    $q->whereHas('sentChats', function($q2) use ($userId) {
                            $q2->where('receiver_id', $userId)
                            ->where('deleted_by_receiver', false);
                        })
                    ->orWhereHas('receivedChats', function($q2) use ($userId) {
                            $q2->where('sender_id', $userId)
                            ->where('deleted_by_sender', false);
                        });
                })
                ->withCount([
                    'unreadMessages as unread_messages_count' => function($q) use ($userId) {
                        $q->where('receiver_id', $userId)
                        ->where('deleted_by_receiver', false);
                    }
                ])
                ->addSelect([
                    'last_message' => $lastMessageSub,
                    'last_message_at' => $lastMessageAtSub,
                ])
                ->orderByDesc('last_message_at')
                ->paginate(20);

            // All contacts (colleagues + clients) - exclude me
            $contacts = User::where('id', '!=', $userId)
                ->withCount([
                    'unreadMessages as unread_messages_count' => function($q) use ($userId) {
                        $q->where('receiver_id', $userId)
                        ->where('deleted_by_receiver', false);
                    }
                ])
                ->orderBy('role')
                ->paginate(20);

            return response()->json([
                'chats' => $chats,
                'contacts' => $contacts,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Fetching error: ' . $e->getMessage());
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

    public function destroy($id)
    {
        try {
            $chat = Chat::where([['receiver_id',$id],['sender_id',auth()->id()]])
                ->orWhere([['receiver_id',auth()->id()],['sender_id',$id]])
                ->get();
            foreach ($chat as $c) {
                $c->deleted_by_sender = true;
                $c->save();
            }

            return response()->json(['message' => 'Client deleted successfully'], 201);
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
