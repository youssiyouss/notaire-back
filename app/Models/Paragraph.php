<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paragraph extends Model
{
    protected $fillable = [
        'type',
        'title',
        'content',
        'contract_subtype_id',
        'created_by',
        'updated_by'
    ];

    // Define the inverse relationship to User
     public function contractSubType()
    {
        return $this->belongsTo(ContractSubtype::class);
    }

}
