<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg,ico|max:4048',
            'nom_commercial' => 'nullable|string|max:255',
            'forme_juridique' => 'required|string|max:255',
            'capital_social' => 'nullable|numeric|min:0',
            'adresse_siege' => 'nullable|string|max:500',
            'registre_commerce' => 'required|string|max:255',
            'date_rc' => 'nullable|date',
            'wilaya_rc' => 'nullable|string|max:100',
            'nif' => 'nullable|string|max:100',
            'nis' => 'nullable|string|max:100',
            'boal' => 'nullable|string|max:100',
            'ai' => 'nullable|string|max:100',
            'date_creation' => 'nullable|date',
            'activite_principale' => 'nullable|string|max:255',
            'owner' => 'required|exists:users,id',
        ];
    }
}
