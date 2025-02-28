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
        'updated_by',
        'created_by',
    ];
}
