<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'type',
        'reason',
        'status',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'responded_at' => 'datetime',
    ];

    // Employee who requested leave
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Admin who approved/denied
    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}

