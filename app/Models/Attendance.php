<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'hours',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
    ];

     // Employee concerned
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Manager/HR who created the record
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
