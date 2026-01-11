<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id', 'content','receiptPath','price' ,'notaire_id', 'status','signature_date','pdf_path','word_path','summary_word_path','summary_pdf_path','progress_steps','created_by', 'updated_by'
    ];

    protected $dates = ['deleted_at'];

    protected $casts = [
        'progress_steps' => 'array',
        'signature_date' => 'datetime',
    ];

    public function template() {
        return $this->belongsTo(ContractTemplate::class);
    }

    public function attributes()
    {
        return $this->hasMany(AttributeValues::class)->with('attribute');
    }

    public function clientUsers()
    {
        return $this->belongsToMany(User::class, 'contract_client', 'contract_id', 'client_id');
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
