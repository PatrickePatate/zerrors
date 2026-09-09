<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InviteController extends Controller
{
    /**
     * Create a pending invite for the organization. Owners/admins only.
     */
    public function store(Request $request, Organization $organization)
    {
        $this->authorizeManage($request, $organization);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,member'],
        ]);

        if ($organization->users()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => 'This person is already a member.']);
        }

        $invite = $organization->invites()->create([
            'email' => $data['email'],
            'role' => $data['role'],
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invite->email)->queue(new OrganizationInviteMail($invite));

        app(AuditLogger::class)->log($organization, $request->user(), 'invite.created', $invite->email, ['role' => $invite->role]);

        // Also surface the link directly in the UI, so invites stay usable
        // even when the configured mailer can't actually reach the invitee.
        return back()->with('invite_link', route('invites.accept', $invite->token));
    }

    public function destroy(Request $request, Organization $organization, OrganizationInvite $invite)
    {
        $this->authorizeManage($request, $organization);
        abort_unless($invite->organization_id === $organization->id, 404);

        $invite->delete();

        return back();
    }

    /**
     * Public landing page for an invite link (works for guests and logged-in users).
     */
    public function show(string $token): View|RedirectResponse
    {
        $invite = OrganizationInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isPending()) {
            return view('auth.invite-invalid');
        }

        if (Auth::check()) {
            $invite->organization->users()->syncWithoutDetaching([Auth::id() => ['role' => $invite->role]]);
            $invite->forceFill(['accepted_at' => now()])->save();

            return redirect()->route('organizations.projects.index', $invite->organization)
                ->with('status', "You've joined {$invite->organization->name}.");
        }

        session(['invite_token' => $token]);

        return view('auth.invite-accept', ['invite' => $invite]);
    }

    protected function authorizeManage(Request $request, Organization $organization): void
    {
        $role = $organization->roleFor($request->user());
        abort_unless(in_array($role, ['owner', 'admin'], true), 403);
    }
}
