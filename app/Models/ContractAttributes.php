<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractAttributes extends Model
{

    protected $fillable = ['contract_id', 'name', 'value','created_by','updated_by'];

    public function get_contract() {
        return $this->belongsTo(Contract::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
