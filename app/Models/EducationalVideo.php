<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationalVideo extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'audience', 'source','video_path','video_url', 'thumbnail','category','duration','created_by', 'updated_by'];

    protected $dates = ['deleted_at', 'created_at','updated_at'];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    // helper pour URL publique
    public function getPublicUrlAttribute()
    {
        if ($this->source === 'Youtube') {
            return $this->video_url;
        } 
        return $this->video_path ? asset('storage/' . $this->video_path) : null;
    }
}
