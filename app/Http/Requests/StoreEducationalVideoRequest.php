<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationalVideoRequest extends FormRequest
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
    public function rules()
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'audience' => 'required',
            'category' => 'required',
            'duration'=> 'nullable|integer',
            'thumbnail' => 'nullable|mimes:jpg,png,svg,gif|max:4096',
            'source' => 'required|in:Ordinateur,Youtube',  
            'video_path' => 'required_if:source,Ordinateur|file|mimes:mp4,webm,ogg|max:512000', // max ~500MB (taille en KB)
            'video_url' => 'required_if:source,Youtube|nullable|url',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Un titre est requis pour le PDF.',
            'category.required' => 'La catégorie du PDF est requise.',
            'audience.required' => 'Veuillez sélectionner le public cible de ce fichier',
            'thumbnail.mimes' => 'La vignette doit être une: jpg,png,svg ou gif.',
            'thumbnail.max' => 'La vignette ne doit pas dépasser 4 Mo.', 
            'video_path.required_if' => 'Un fichier vidéo est requis pour les vidéos locales.',
            'video_url.required_if' => 'L’URL YouTube est requise pour les vidéos YouTube.',
            'video_path.max' => 'La taille de la vidéo ne doit pas dépasser 500 MB.',
            'video_path.mimes' => 'L\'extension vidéo doit être : .mp4, .webm ou .ogg',
        ];
    }
}
