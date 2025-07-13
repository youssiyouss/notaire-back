<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{

    protected $fillable = ['attribute_name','source_field','group_id'];

    public function group()
    {
        return $this->belongsTo(TemplateGroup::class, 'group_id');
    }
}
