<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValues extends Model
{
    protected $fillable = ['contract_id','attribute_id', 'name', 'value'];

    public function get_contract() {
        return $this->belongsTo(Contract::class);
    }

    public function get_attribute() {
        return $this->belongsTo(Attribute::class);
    }

}
