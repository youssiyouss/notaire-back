<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSubtype extends Model
{
    protected $fillable = ['contract_type_id', 'name','created_by','updated_by'];

    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }
}
