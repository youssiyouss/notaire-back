<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User_Document extends Model
{
    protected $table = 'user_documents';

    protected $fillable = [
        'user_id',
        'image',
        'document_type',
        'id_document',
        'date_emission_document',
        'lieu_emission_document',
        'updated_by',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

      public function updator()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
