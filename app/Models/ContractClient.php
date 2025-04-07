<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractClient extends Model
{

    protected $table ="contract_client";

    protected $fillable = ['client_state', 'contract_id', 'client_id'];

    public function contract() {
        return $this->belongsTo(Contract::class);
    }

}
