<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'string', 'max:500'],
            'description'  => ['sometimes', 'string'],
            'due_date'     => ['nullable', 'date'],
            'assigned_to'  => ['nullable', 'exists:users,id'],
            'contract_id'  => ['nullable', 'exists:contracts,id'],
            'status'       => ['sometimes', 'string', 'in:à_faire,en_cours,en_attente,terminé'],
            'priorité'     => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max'         => 'Le titre ne doit pas dépasser 500 caractères.',
            'assigned_to.exists'=> 'L\'utilisateur assigné n\'existe pas.',
            'contract_id.exists'=> 'Le contrat sélectionné n\'existe pas.',
            'status.in'         => 'Le statut doit être l\'une des valeurs suivantes : à_faire, en_cours, en_attente, terminé.',
            'priorité.integer'  => 'La priorité doit être un entier.',
        ];
    }
}
