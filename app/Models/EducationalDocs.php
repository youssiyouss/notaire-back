<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationalDocs extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'audience', 'file_path','category','created_by', 'updated_by'];

    protected $dates = ['deleted_at', 'created_at','updated_at'];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
