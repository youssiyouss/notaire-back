<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractAttributes extends Model
{
    protected $table = 'template_attributes';

    protected $fillable = ['contract_id', 'name', 'value'];

    public function get_contract() {
        return $this->belongsTo(Contract::class);
    }
}
