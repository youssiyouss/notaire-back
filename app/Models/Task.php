<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'due_date', 'assigned_to', 'contract_id', 'status', 'priorité','created_by','updated_by'
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function assignedTo() {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function contract() {
        return $this->belongsTo(Contract::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
