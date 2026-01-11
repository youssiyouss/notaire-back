<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContractProgressController extends Controller
{
    /**
     * Get progress steps for a contract
     */
    public function index($contractId)
    {
        try {
            $contract = Contract::findOrFail($contractId);
            
            $steps = $contract->progress_steps ?? $this->getDefaultSteps();

            return response()->json([
                'progress_steps' => $steps
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching contract progress: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Initialize default progress steps for a contract
     */
    public function initializeSteps($contractId)
    {
        try {
            $contract = Contract::findOrFail($contractId);
            
            if (!$contract->progress_steps) {
                $contract->progress_steps = $this->getDefaultSteps();
                $contract->save();
            }

            return response()->json([
                'message' => 'Étapes de progression initialisées',
                'progress_steps' => $contract->progress_steps
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error initializing progress steps: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a specific progress step
     */
    public function updateStep(Request $request, $contractId, $stepId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'completed' => 'required|boolean',
                'date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $contract = Contract::findOrFail($contractId);
            $steps = $contract->progress_steps ?? $this->getDefaultSteps();

            // Find and update the step
            $stepFound = false;
            foreach ($steps as &$step) {
                if ($step['id'] == $stepId) {
                    $step['completed'] = $request->completed;
                    $step['date'] = $request->completed ? ($request->date ?? date('Y-m-d')) : null;
                    $stepFound = true;
                    break;
                }
            }

            if (!$stepFound) {
                return response()->json([
                    'error' => 'Étape non trouvée'
                ], Response::HTTP_NOT_FOUND);
            }

            $contract->progress_steps = $steps;
            $contract->save();

            // Auto-update contract status based on progress
            $this->updateContractStatus($contract);

            return response()->json([
                'message' => 'Étape mise à jour avec succès',
                'progress_steps' => $contract->progress_steps
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error updating progress step: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update progress steps
     */
    public function updateSteps(Request $request, $contractId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'progress_steps' => 'required|array',
                'progress_steps.*.id' => 'required',
                'progress_steps.*.label' => 'required|string',
                'progress_steps.*.completed' => 'required|boolean',
                'progress_steps.*.description' => 'required|string',
                'progress_steps.*.date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $contract = Contract::findOrFail($contractId);
            $contract->progress_steps = $request->progress_steps;
            $contract->save();

            // Auto-update contract status
            $this->updateContractStatus($contract);

            return response()->json([
                'message' => 'Étapes de progression mises à jour',
                'progress_steps' => $contract->progress_steps
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error updating progress steps: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark next step as in progress automatically
     */
    public function advanceToNextStep($contractId)
    {
        try {
            $contract = Contract::findOrFail($contractId);
            $steps = $contract->progress_steps ?? $this->getDefaultSteps();

            // Find first incomplete step and mark as in progress
            foreach ($steps as &$step) {
                if (!$step['completed']) {
                    // Mark current step as completed
                    $step['completed'] = true;
                    $step['date'] = date('Y-m-d');
                    break;
                }
            }

            $contract->progress_steps = $steps;
            $contract->save();

            $this->updateContractStatus($contract);

            return response()->json([
                'message' => 'Progression avancée',
                'progress_steps' => $contract->progress_steps
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error advancing progress: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Auto-update contract status based on progress
     */
    private function updateContractStatus($contract)
    {
        // Auto-update progress based on contract data
        $steps = $this->updateProgressBasedOnContract($contract, $contract->progress_steps);
        $contract->progress_steps = $steps;
        
        if (!$contract->progress_steps) return;

        $total = count($contract->progress_steps);
        $completed = 0;

        foreach ($contract->progress_steps as $step) {
            if ($step['completed']) {
                $completed++;
            }
        }

        // Update contract status based on completion
        if ($completed === 0) {
            $contract->status = 'pending';
        } elseif ($completed === $total) {
            $contract->status = 'completed';
        } else {
            $contract->status = 'in_progress';
        }

        $contract->save();
    }

    /**
     * Update progress steps based on contract data
     */
    private function updateProgressBasedOnContract($contract, $steps)
    {
        if (!$steps) return $steps;

        foreach ($steps as &$step) {            // \u00c9tape 3: Validation notaire - completed if signature_date is set
            if ($step['id'] == 3 && $contract->signature_date) {
                $step['completed'] = true;
                $step['date'] = date('Y-m-d');
            }
            // Étape 4: Signature - completed if signature_date is set
            if ($step['id'] == 4 && $contract->signature_date) {
                $step['completed'] = true;
                $step['date'] = $contract->signature_date->format('Y-m-d');
            }

            // Étape 5: Dossier clôturé - completed if payment_status is 'paid'
            if ($step['id'] == 5 && $contract->payment_status === 'paid') {
                $step['completed'] = true;
                $step['date'] = $contract->updated_at->format('Y-m-d');
            }
        }

        return $steps;
    }

    /**
     * Get default progress steps
     */
    private function getDefaultSteps()
    {
        return [
            [
                'id' => 1,
                'label' => 'Client enregistré',
                'completed' => true,
                'date' => date('Y-m-d'),
                'description' => 'Le client a été ajouté à notre base de données'
            ],
            [
                'id' => 2,
                'label' => 'Contrat créé',
                'completed' => true,
                'date' => date('Y-m-d'),
                'description' => 'Le contrat a été créé et généré'
            ],
            [
                'id' => 3,
                'label' => 'Validation notaire',
                'completed' => true,
                'date' => date('Y-m-d'),
                'description' => 'Le notaire a validé le dossier'
            ],
            [
                'id' => 4,
                'label' => 'Signature',
                'completed' => false,
                'date' => null,
                'description' => 'En attente de la date de signature'
            ],
            [
                'id' => 5,
                'label' => 'Dossier clôturé',
                'completed' => false,
                'date' => null,
                'description' => 'Le dossier sera clôturé après le paiement'
            ]
        ];
    }
}
