<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'template_id', 'content', 'status','pdf_path','created_by', 'updated_by'
    ];


    public function template() {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes() {
        return $this->hasMany(ContractAttributes::class);
    }

    public function clients()
    {
        return $this->belongsToMany(User::class, 'contract_client', 'contract_id', 'client_id'); // Si tu veux garder les infos du pivot
    }


    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
