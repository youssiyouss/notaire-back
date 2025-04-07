<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'client_id', 'template_id', 'content','partAstate','partBstate', 'created_by', 'updated_by'
    ];


    public function template() {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes() {
        return $this->hasMany(ContractAttributes::class);
    }

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'contract_client'); // Specify the correct pivot table name
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
