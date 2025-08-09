<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Events\TaskUpdated;
use App\Notifications\TaskActionNotification;
use App\Events\NewTask;
use App\Notifications\NewTaskNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskController extends Controller
{
    
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $user = auth()->user();

            $tasks = Task::with(['assignedTo', 'contract.template','creator','editor'])
                        ->where('assigned_to', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->get();

            return response()->json([
                'tasks' => $tasks->groupBy('status')
            ]);

        }catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request)
    {
        try {
            $this->authorize('create', Task::class);
            $task = Task::create([
                'title'            => $request->title,
                'description'      => $request->description,
                'due_date'         => $request->due_date,
                'assigned_to'      => $request->assigned_to,
                'contract_id'      => $request->contract_id,
                'status'           => 'à_faire',
                'created_by'       => Auth::id(),
            ]);

                $message = [
                    'key' => 'notif.new_task',
                    'params' => ['task' => $task->title],
                ];
                $notifiable = User::findOrFail($task->assigned_to);
            if (!empty($notifiable)) {
                Notification::send($notifiable, new NewTaskNotification($task, $message));
            }

            broadcast(new NewTask($task,$notifiable))->toOthers();


            return response()->json([
                'message' => __('Task created successfully.'),
                'task'    => $task,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => __('An unexpected error occurred.'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        try { 
            $this->authorize('update', $task);

            $data = $request->only([
                'title', 'description', 'due_date', 'assigned_to', 'contract_id', 'status', 'priorité',
            ]);
            // Keep a copy of original values
            $original = $task->getOriginal(); // before update
            $task->update([
                'title'        => $request->title ?? $task->title,
                'description'  => $request->description ?? $task->description,
                'due_date'     => $request->due_date ?? $task->due_date,
                'assigned_to'  => $request->assigned_to ?? $task->assigned_to,
                'contract_id'  => $request->contract_id ?? $task->contract_id,
                'status'       => $request->status ?? $task->status,
                'priorité'     => $request->priorité ?? $task->priorité,
                'updated_by'   => Auth::id(),
            ]);  // Update model attributes
            $changes = $task->getChanges();// This only includes changed fields

            $notifiables = [];
            if (array_key_exists('due_date', $changes)) {
                $message = [
                    'key' => 'notif.due_date_updated',
                    'params' => ['date' => $task->due_date],
                ];
                $notifiables[] = User::findOrFail($task->assigned_to);

            } elseif (array_key_exists('assigned_to', $changes)) {
                $old = User::findOrFail($original['assigned_to']);
                $new = User::findOrFail($request->assigned_to);
                $message = [
                    'key' => 'notif.assigned_to_changed',
                    'params' => ['name' => $new->nom . ' ' . $new->prenom],
                ];
                $notifiables = [$old, $new];
            } elseif (array_key_exists('status', $changes)) {
                $message = [
                    'key' => 'notif.status_changed',
                    'params' => ['status' => $task->status],
                ];
                $notifiables = collect([
                    User::findOrFail($task->created_by),
                    User::findOrFail($task->assigned_to)
                ])->unique('id')->values();


            } elseif (array_key_exists('priorité', $changes)) {
                $message = ['key' => 'notif.priority_changed', 'params' => []];
                $notifiables[] = User::findOrFail($task->assigned_to);

            } else {
                $message = ['key' => 'notif.details_changed', 'params' => []];
                $notifiables[] = User::findOrFail($task->assigned_to);
            }

            if (!empty($notifiables)) {
                Notification::send($notifiables, new TaskActionNotification($task, $message));
            }

            broadcast(new TaskUpdated($task,$notifiables))->toOthers();

            return response()->json([
                'message' => 'Tâche mise à jour avec succès.',
                'task' => $task
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour tâche : ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la tâche.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        try {
            
            $this->authorize('delete', $task);

            $task->delete();

            return response()->json([
                'message' => "Tache supprimé avec succés"
            ]);

        }catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
}
