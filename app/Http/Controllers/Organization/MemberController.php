<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('fault.organizations.members', ['organization' => $organization]);
    }

    public function updateRole(Request $request, Organization $organization, User $user)
    {
        $actingRole = $organization->roleFor($request->user());
        abort_unless(in_array($actingRole, ['owner', 'admin'], true), 403);

        $data = $request->validate(['role' => ['required', 'in:owner,admin,member']]);
        $targetRole = $organization->roleFor($user);

        abort_unless($targetRole !== null, 404);

        // Admins can promote/demote members but can't touch owners or grant ownership.
        if ($actingRole !== 'owner' && ($targetRole === 'owner' || $data['role'] === 'owner')) {
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
        $role = $organization->roleFor($request->user());
        abort_unless(in_array($role, ['owner', 'admin'], true), 403);

        $targetRole = $organization->roleFor($user);

        if ($targetRole === 'owner' && $organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            return back()->withErrors(['user' => 'An organization needs at least one owner.']);
        }

        $organization->users()->detach($user->id);

        app(AuditLogger::class)->log($organization, $request->user(), 'member.removed', $user->email);

        return back();
    }
}
