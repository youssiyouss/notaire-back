<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = [
        'contract_type_id',
        'contract_subtype',
        'taxe_type',
        'taxe_pourcentage',
        'attributes',
        'part_all_transformations',
        'part_a_transformations',
        'part_b_transformations',
        'content',
        'created_by',
        'updated_by',

        // Newly added price/percentage fields
        'original',
        'copy',
        'documentation',
        'publication',
        'consultation',
        'consultationFee',
        'workFee',
        'others',

        'stamp',
        'registration',
        'advertisement',
        'rkm',
        'announcements',
        'deposit',
        'boal',
        'registration_or_cancellation',
    ];

    public function contracts() {
        return $this->hasMany(Contract::class);
    }

    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor() {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
