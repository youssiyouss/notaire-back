<?php

namespace App\Http\Controllers\clientSide;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClientContractController extends Controller
{
    /**
     * Get all contracts for the authenticated client
     */
    public function index()
    {
        try {
            $user = Auth::guard('api')->user();
            
            // Get contract_clients for this user, then get the actual contracts
            $contractClients = \App\Models\ContractClient::with(['contract.template.contractType'])
                ->where('client_id', $user->id)
                ->get();

            $contractsList = $contractClients->map(function($contractClient) {
                $contract = $contractClient->contract;
                if (!$contract) return null;

                $contractTypeName = '';
                if ($contract->template) {
                    if ($contract->template->contractType) {
                        $contractTypeName = $contract->template->contractType->nom;
                    }
                    if ($contract->template->contract_subtype) {
                        $contractTypeName .= ($contractTypeName ? ' - ' : '') . $contract->template->contract_subtype;
                    }
                }
                $contractType = $contractTypeName ?: 'Type non défini';

                return [
                    'id' => $contract->id,
                    'contract_number' => 'NOT-' . date('Y', strtotime($contract->created_at)) . '-' . str_pad($contract->id, 5, '0', STR_PAD_LEFT),
                    'contract_type' => $contractType,
                    'status' => $contract->status ?? 'En cours',
                    'created_at' => $contract->created_at,
                    'role' => $contractClient->type // 'buyer' or 'seller' from pivot table
                ];
            })->filter(); // Remove null entries

            return response()->json([
                'contracts' => $contractsList->values()
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error fetching client contracts: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des contrats'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get detailed information about a specific contract
     */
    public function show($id)
    {
        try {
            $user = Auth::guard('api')->user();
            
            // Verify user has access to this contract
            $contractClient = \App\Models\ContractClient::where('contract_id', $id)
                ->where('client_id', $user->id)
                ->first();

            if (!$contractClient) {
                return response()->json([
                    'error' => 'Vous n\'avez pas accès à ce contrat'
                ], Response::HTTP_FORBIDDEN);
            }

            // Get contract with relationships
            $contract = Contract::with([
                'template.contractType',
                'clients.client',
                'notaire'
            ])->findOrFail($id);

            // Get all clients with their roles from contract_clients
            $clients = $contract->clients->map(function($contractClient) {
                return [
                    'role' => $contractClient->type,
                    'name' => $contractClient->client->nom . ' ' . $contractClient->client->prenom,
                    'email' => $contractClient->client->email
                ];
            });

            // Use progress_steps from contract or default steps
            if ($contract->progress_steps && is_array($contract->progress_steps) && count($contract->progress_steps) > 0) {
                $statuses = $contract->progress_steps;
            } else {
                $statuses = $this->getDefaultStatuses();
            }

            // Auto-update progress based on contract data
            $statuses = $this->updateProgressBasedOnContract($contract, $statuses);

            $contractTypeName = '';
            if ($contract->template) {
                if ($contract->template->contractType) {
                    $contractTypeName = $contract->template->contractType->name;
                }
                if ($contract->template->contract_subtype) {
                    $contractTypeName .= ($contractTypeName ? ' : ' : '') . $contract->template->contract_subtype;
                }
            }
            $contractType = $contractTypeName ?: 'Type non défini';

            return response()->json([
                'contract' => [
                    'id' => $contract->id,
                    'contract_number' => 'NOT-' . date('Y', strtotime($contract->created_at)) . '-' . str_pad($contract->id, 5, '0', STR_PAD_LEFT),
                    'contract_type' => $contractType,
                    'created_date' => $contract->created_at->format('Y-m-d'),
                    'current_status' => $contract->status ?? 'En cours de traitement',
                    'notary' => $contract->notaire 
                        ? ($contract->notaire->nom . ' ' . $contract->notaire->prenom)
                        : 'Non assigné',
                    'payment_status' => $contract->payment_status ?? 'En attente',
                    'price' => $contract->price ?? null,
                    'clients' => $clients,
                    'statuses' => $statuses
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Contrat non trouvé'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Error fetching contract details: ' . $e->getMessage());
            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement du contrat'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get default statuses if no tasks are defined
     */
    private function getDefaultStatuses()
    {
        return [
            [
                'id' => 1,
                'label' => 'Client enregistré',
                'completed' => true,
                'date' => date('Y-m-d'),
                'description' => 'Vous avez été ajouté à notre base de données'
            ],
            [
                'id' => 2,
                'label' => 'Contrat créé',
                'completed' => true,
                'date' => date('Y-m-d'),
                'description' => 'Votre contrat a été créé et généré'
            ],
            [
                'id' => 3,
                'label' => 'Validation notaire',
                'completed' => false,
                'date' => null,
                'description' => 'Le notaire est en train de valider votre dossier'
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

    /**
     * Update progress steps based on contract data
     */
    private function updateProgressBasedOnContract($contract, $statuses)
    {
        foreach ($statuses as &$status) {            // \u00c9tape 3: Validation notaire - completed if signature_date is set
            if ($status['id'] == 3 && $contract->signature_date) {
                $status['completed'] = true;
                $status['date'] = date('Y-m-d');
            }
            // Étape 4: Signature - completed if signature_date is set
            if ($status['id'] == 4 && $contract->signature_date) {
                $status['completed'] = true;
                $status['date'] = $contract->signature_date;
            }

            // Étape 5: Dossier clôturé - completed if payment_status is 'paid'
            if ($status['id'] == 5 && $contract->payment_status === 'Payé') {
                $status['completed'] = true;
                $status['date'] = $contract->updated_at;
            }
        }

        return $statuses;
    }
}
