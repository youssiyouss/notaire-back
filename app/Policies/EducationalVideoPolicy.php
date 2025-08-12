<?php

namespace App\Policies;

use App\Models\EducationalVideo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EducationalVideoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EducationalVideo $educationalVideo): bool
    {
        return $educationalVideo->audience === 'Tout' || $user->role === 'Admin';
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
    public function update(User $user, EducationalVideo $educationalVideo): bool
    {
        return $user->role !== 'Client';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EducationalVideo $educationalVideo): bool
    {
        return $user->role === 'Admin' || $video->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EducationalVideo $educationalVideo): bool
    {
        return $user->role === 'Admin' || $video->created_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EducationalVideo $educationalVideo): bool
    {
        return $user->role === 'Admin' || $video->created_by === $user->id;
    }
}
