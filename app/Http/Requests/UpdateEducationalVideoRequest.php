<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEducationalVideoRequest extends FormRequest
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
            'audience' => 'nullable',
            'category' => 'nullable',
            'duration'=> 'nullable|integer',
            'thumbnail' => 'nullable|mimes:jpg,png,svg,gif|max:4096',
            'source' => 'nullable|in:Ordinateur,Youtube',  
            'video_path' => 'nullable|file|mimes:mp4,webm,ogg|max:512000', // max ~500MB (taille en KB)
            'video_url' => 'nullable|url',
        ];
    }

    public function messages()
    {
        return [ 
            'thumbnail.mimes' => 'La vignette doit être une: jpg,png,svg ou gif.',
            'thumbnail.max' => 'La vignette ne doit pas dépasser 4 Mo.',  
            'video_path.max' => 'La taille de la vidéo ne doit pas dépasser 500 MB.',
            'video_path.mimes' => 'L\'extension vidéo doit être : .mp4, .webm ou .ogg',
        ];
    }
}
