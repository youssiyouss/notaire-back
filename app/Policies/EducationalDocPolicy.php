<?php

namespace App\Policies;

use App\Models\EducationalDocs;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EducationalDocPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EducationalDocs $educationalDocs): bool
    {
        return $educationalDocs->audience === 'Tout' || $user->role === 'Admin';
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role !== 'Client';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EducationalDocs $educationalDocs): bool
    {
        return in_array($user->role, ['Admin', 'Notaire', 'Assistant Notaire' ,'Receptionniste']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EducationalDocs $educationalDocs): bool
    {
        return $user->role === 'Admin' ||$user->role === 'Notaire' || $educationalDocs->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EducationalDocs $educationalDocs): bool
    {
        return $user->role === 'Admin' ||$user->role === 'Notaire' || $educationalDocs->created_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EducationalDocs $educationalDocs): bool
    {
        return $user->role === 'Admin' ||$user->role === 'Notaire' || $educationalDocs->created_by === $user->id;
    }
}
