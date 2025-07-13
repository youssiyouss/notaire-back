<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractClient extends Model
{

    protected $table ="contract_client";

    protected $fillable = ['contract_id', 'client_id','type'];

    public function contract() {
        return $this->belongsTo(Contract::class,'contract_id');
    }

    public function client(){
        return $this->belongsTo(User::class,'client_id');
    }

}
