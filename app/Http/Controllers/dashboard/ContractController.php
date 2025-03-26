<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\Contract;
use App\Models\User;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $contracts = Contract::with('sub_categories')->get();

            return response()->json([
                'contracts' => $contracts,
           ], 200);

        } catch (\Exception $e) {
            Log::error('Fetching error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function search_users(Request $request)
    {
        $query = $request->input('search'); // Correct way to retrieve POST data
         \Log::info('Search query: ' . $request->input('search'));

        $users = User::where('nom', 'LIKE', "%{$query}%")
            ->orWhere('prenom', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('date_de_naissance', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get();

        \Log::info("Users found: " . json_encode($users));

        return response()->json($users);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         try{
            $validated = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'contract_subtype_id' => 'required|exists:contract_subtypes,id',
                'content' => 'required|json',
            ]);

            $contract = Contract::create([
                'client_id' => $validated['client_id'],
                'contract_subtype_id' => $validated['contract_subtype_id'],
                'status' => $validated['status'],
                'content' => $validated['content'],
                'created_by' => auth()->id(),
            ]);

            return response()->json($contract, 201);
        }catch(\Throwable $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }catch (\Exception $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
