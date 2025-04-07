<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

   protected $fillable = [
        'user_id',
        'nationalite',
        'lieu_de_naissance',
        'nom_maternelle',
        'prenom_mere',
        'prenom_pere',
        'emploi',
    ];

    // Define the inverse relationship to User
    public function parent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_client'); // Specify the correct pivot table name
    }

}
