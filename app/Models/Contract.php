<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id', 'content','receiptPath','price' ,'notaire_id', 'status','pdf_path','word_path','created_by', 'updated_by'
    ];

    protected $dates = ['deleted_at'];

    public function template() {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes()
    {
        return $this->hasMany(AttributeValues::class)->with('attribute');
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

    public function clientUsers()
    {
        return $this->belongsToMany(User::class, 'contract_client', 'contract_id', 'client_id');
    }

}
