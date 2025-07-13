<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordTransformation extends Model
{
    protected $fillable = [
        'placeholder',
        'masculine',
        'feminine',
        'masculine_plural',
        'feminine_plural',
        'group_id'  // Changed from 'group'
    ];

    // Remove template_id unless you actually need it
    public function group()
    {
        return $this->belongsTo(TemplateGroup::class, 'group_id');
    }
}
