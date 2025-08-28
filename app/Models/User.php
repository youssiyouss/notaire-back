<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'tel',
        'adresse',
        'password',
        'sexe',
        'date_de_naissance',
        'role',
        'ccp',
        'salaire',
        'date_virement_salaire',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Define the relationship to Client
    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function documents()
    {
        return $this->hasMany(ClientDocument::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updator()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function companies()
    {
        return $this->hasMany(Company::class, 'owner');
    }

    public function unreadMessages()
    {
        return $this->hasMany(Chat::class, 'sender_id')
            ->where('receiver_id', auth()->id())
            ->where('is_read', 0);
    }


    // To get all chats where the user is either sender or receiver
    public function chats()
    {
        return $this->hasMany(Chat::class, 'sender_id')
            ->orWhere('receiver_id', $this->id);
    }

    public function sentChats()
    {
        return $this->hasMany(Chat::class, 'sender_id');
    }

    public function receivedChats()
    {
        return $this->hasMany(Chat::class, 'receiver_id');
    }



}
