<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'template_id', 'content','receiptPath','price' ,'notaire_id', 'status','pdf_path','created_by', 'updated_by'
    ];


    public function template() {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes() {
        return $this->hasMany(ContractAttributes::class);
    }

    public function clients()
    {
        return $this->hasMany(ContractClient::class);
    }

    public function notaire() {
        return $this->belongsTo(User::class, 'notaire_id');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
