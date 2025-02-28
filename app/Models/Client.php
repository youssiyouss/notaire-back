<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

   protected $fillable = [
        'nationalite',
        'lieu_de_naissance',
        'nom_maternelle',
        'prenom_mere',
        'prenom_pere',
        'numero_acte_naissance',
        'type_carte',
        'date_emission_carte',
        'lieu_emission_carte',
        'emploi',
        'user_id'
    ];

    // Define the inverse relationship to User
    public function parent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
