<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('clients', function ($user) {
    return $user->role !== 'Client';
});

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/*
|--------------------------------------------------------------------------
| Global users presence channel
|--------------------------------------------------------------------------
| All authenticated users can join this room. Frontend uses membership to
| display online/offline badges in contacts/conversations lists.
*/
Broadcast::channel('users.presence', function ($user) {
    return [
        'id' => (int) $user->id,
        'name' => trim(($user->nom ?? '') . ' ' . ($user->prenom ?? '')),
        'role' => $user->role,
    ];
});

/*
|--------------------------------------------------------------------------
| Presence channel for client support availability
|--------------------------------------------------------------------------
| - Clients can only join their own support presence room.
| - Support/admin users (role !== 'Client') can join any client room.
| - Returning an array is required for presence channels so members are
|   visible to each other with lightweight profile info.
*/
Broadcast::channel('support.presence.{clientId}', function ($user, $clientId) {
    $isSameClient = (int) $user->id === (int) $clientId;
    $isSupportUser = $user->role !== 'Client';

    if (!($isSameClient || $isSupportUser)) {
        return false;
    }

    return [
        'id' => (int) $user->id,
        'name' => trim(($user->nom ?? '') . ' ' . ($user->prenom ?? '')),
        'role' => $user->role,
    ];
});
