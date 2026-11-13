<?php

namespace App\Http\Controllers\Organization;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('fault.organizations.members', ['organization' => $organization]);
    }

    public function updateRole(Request $request, Organization $organization, User $user)
    {
        Gate::authorize('manage', $organization);

        $data = $request->validate(['role' => ['required', Rule::enum(OrganizationRole::class)]]);
        $targetRole = $organization->roleFor($user);

        abort_unless($targetRole !== null, 404);

        // Admins can promote/demote members but can't touch owners or grant ownership.
        if (! $request->user()->can('own', $organization) && ($targetRole === 'owner' || $data['role'] === 'owner')) {
            abort(403);
        }

        if ($targetRole === 'owner' && $data['role'] !== 'owner'
            && $organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            return back()->withErrors(['user' => 'An organization needs at least one owner.']);
        }

        $organization->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        app(AuditLogger::class)->log($organization, $request->user(), 'member.role_updated', $user->email, [
            'from' => $targetRole,
            'to' => $data['role'],
        ]);

        return back();
    }

    public function destroy(Request $request, Organization $organization, User $user)
    {
        Gate::authorize('manage', $organization);

        $targetRole = $organization->roleFor($user);

        abort_unless($targetRole !== null, 404);
        abort_if($targetRole === 'owner' && ! $request->user()->can('own', $organization), 403);

        if ($targetRole === 'owner' && $organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            return back()->withErrors(['user' => 'An organization needs at least one owner.']);
        }

        $organization->users()->detach($user->id);

        app(AuditLogger::class)->log($organization, $request->user(), 'member.removed', $user->email);

        return back();
    }
}
