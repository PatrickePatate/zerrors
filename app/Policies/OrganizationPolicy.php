<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Any member, whatever their role.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->roleEnumFor($user) !== null;
    }

    /**
     * Owners, admins and members: change issues, comment, create projects.
     */
    public function write(User $user, Organization $organization): bool
    {
        return $organization->roleEnumFor($user)?->canWrite() === true;
    }

    /**
     * Owners and admins.
     */
    public function manage(User $user, Organization $organization): bool
    {
        return $organization->roleEnumFor($user)?->canManage() === true;
    }

    /**
     * Owners only.
     */
    public function own(User $user, Organization $organization): bool
    {
        return $organization->roleEnumFor($user)?->isOwner() === true;
    }
}
