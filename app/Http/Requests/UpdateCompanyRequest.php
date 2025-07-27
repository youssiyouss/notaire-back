<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'nom_commercial' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'forme_juridique' => ['nullable', 'string'],
            'capital_social' => ['nullable', 'numeric'],
            'adresse_siege' => ['nullable', 'string'],
            'registre_commerce' => ['nullable', 'string'],
            'date_rc' => ['nullable', 'date'],
            'wilaya_rc' => ['nullable', 'string'],
            'nif' => ['nullable', 'string'],
            'nis' => ['nullable','string','max:100'],
            'boal' => ['nullable','string','max:100'],
            'ai' => ['nullable','string','max:100'],
            'date_creation' => ['nullable','date'],
            'activite_principale' => ['nullable', 'string'],
            'owner' => ['nullable', 'exists:users,id'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4048'],
        ];
    }
}
