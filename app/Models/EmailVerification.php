<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailVerification extends Model
{
    use HasFactory;

    // Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'email',
        'token',
        'expires_at',
    ];

    // Optionally, specify the table name if it's not following conventions
    protected $table = 'email_verifications';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
