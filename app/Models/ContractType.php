<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    protected $fillable = ['name','created_by','updated_by'];

    public function subtypes()
    {
        return $this->hasMany(ContractSubtype::class);
    }
}
