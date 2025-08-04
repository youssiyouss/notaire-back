<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'title' => 'required|string|max:500',
            'description' => 'required|string',
            'status' => 'required|in:à_faire,en_cours,en_attente,terminé',
            'assigned_to' => 'required',
            'contract_id' => 'nullable',
            'due_date' => 'nullable|date'
        ];
    }
}
