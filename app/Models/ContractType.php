<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    protected $fillable = ['name','created_by','updated_by'];

    public function contractTemplates()
    {
        return $this->hasMany(ContractTemplate::class, 'contract_type_id');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
