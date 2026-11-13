<?php

namespace App\Livewire\Concerns;

use App\Models\Organization;
use Illuminate\Support\Facades\Gate;

/**
 * Livewire update requests bypass the org.member route middleware, so every
 * mutating action must authorize explicitly against its organization.
 */
trait AuthorizesOrganizationAccess
{
    protected function authorizeView(Organization $organization): void
    {
        Gate::authorize('view', $organization);
    }

    protected function authorizeWrite(Organization $organization): void
    {
        Gate::authorize('write', $organization);
    }

    protected function authorizeManage(Organization $organization): void
    {
        Gate::authorize('manage', $organization);
    }
}
