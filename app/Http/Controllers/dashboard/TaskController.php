<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $tasks = Task::with(['assignedTo', 'contract.template','creator','editor'])->get();

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
            $task = Task::create([
                'title'            => $request->title,
                'description'      => $request->description,
                'due_date'         => $request->due_date,
                'assigned_to'      => $request->assigned_to,
                'contract_id'      => $request->contract_id,
                'status'           => 'à_faire',
                'created_by'       => Auth::id(),
            ]);

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
            $task->update([
                'title'        => $request->title ?? $task->title,
                'description'  => $request->description ?? $task->description,
                'due_date'     => $request->due_date ?? $task->due_date,
                'assigned_to'  => $request->assigned_to ?? $task->assigned_to,
                'contract_id'  => $request->contract_id ?? $task->contract_id,
                'status'       => $request->status ?? $task->status,
                'priorité'     => $request->priorité ?? $task->priorité,
                'updated_by'   => Auth::id(),
            ]);

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

            $task->delete();

            return response()->json([
                'message' => "Tache supprimé avec succés"
            ]);

        }catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
}
