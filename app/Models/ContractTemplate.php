<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = ['contract_type_id', 'contract_subtype', 'attributes', 'pronoun_transformations', 'content','created_by','updated_by'];

    public function contracts() {
        return $this->hasMany(Contract::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
