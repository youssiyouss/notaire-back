<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'logo',
        'nom_commercial',
        'forme_juridique',
        'capital_social',
        'adresse_siege',
        'registre_commerce',
        'date_rc',
        'wilaya_rc',
        'nif',
        'activite_principale',
        'owner',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date_rc' => 'date',
    ];

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }


}
