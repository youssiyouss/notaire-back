<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationalDocRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'audience' => 'required',
            'category' => 'required',
            'file' => 'required|mimes:pdf|max:4096', // 20MB
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Un titre est requis pour le PDF.',
            'category.required' => 'La catégorie du PDF est requise.',
            'file.required' => 'Le fichier du PDF est requis.',
            'audience.required' => 'Veuillez sélectionner le public cible de ce fichier',
            'file.mimes' => 'Le fichier doit être un PDF.',
            'file.max' => 'Le PDF ne doit pas dépasser 4 Mo.',
        ];
    }
}
