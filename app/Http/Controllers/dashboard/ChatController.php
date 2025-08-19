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
use App\Notifications\NewEducationalAssetNotification;
use App\Events\ChatMessage; 
use App\Events\MessageRead;


class ChatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($userId)
    {
        try {
            $messages = Chat::where(function ($q) use ($userId) {
                            $q->where([['sender_id', auth()->id()],['receiver_id', $userId]]);
                        })
            ->orWhere(function ($q) use ($userId) {
                            $q->where([['sender_id', $userId],['receiver_id', auth()->id()]]);
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
        Log::info("Marked as read & event fired");
        
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


    /**
     * Display the specified resource.
     */
    public function show(string $id)
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
