<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateGroup extends Model
{

    protected $fillable = ['name','template_id'];

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes()
    {
        return $this->hasMany(Attribute::class, 'group_id');
    }

    public function wordTransformations()
    {
        return $this->hasMany(WordTransformation::class, 'group_id');
    }
}
