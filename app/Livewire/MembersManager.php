<?php

namespace App\Livewire;

use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use App\Support\Organization\AuditLogger;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class MembersManager extends Component
{
    use WithPagination;

    public Organization $organization;

    public string $email = '';

    public string $role = 'member';

    public ?string $inviteLink = null;

    public ?string $removalError = null;

    public bool $showInviteModal = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
    }

    #[Computed]
    public function myRole(): ?string
    {
        return $this->organization->roleFor(auth()->user());
    }

    #[Computed]
    public function members()
    {
        return $this->organization->users()->orderBy('name')->paginate(10);
    }

    #[Computed]
    public function invites()
    {
        return $this->organization->invites()->whereNull('accepted_at')->latest()->get();
    }

    protected function authorizeManage(): void
    {
        abort_unless(in_array($this->myRole(), ['owner', 'admin'], true), 403);
    }

    public function invite(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,member'],
        ]);

        if ($this->organization->users()->where('email', $data['email'])->exists()) {
            $this->addError('email', 'This person is already a member.');

            return;
        }

        $invite = $this->organization->invites()->create([
            'email' => $data['email'],
            'role' => $data['role'],
            'invited_by' => auth()->id(),
        ]);

        Mail::to($invite->email)->queue(new OrganizationInviteMail($invite));

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'invite.created', $invite->email, ['role' => $invite->role]);

        $this->inviteLink = route('invites.accept', $invite->token);
        $this->reset('email', 'role');
        $this->showInviteModal = false;
        unset($this->invites);
    }

    public function revokeInvite(int $inviteId): void
    {
        $this->authorizeManage();

        $invite = OrganizationInvite::findOrFail($inviteId);
        abort_unless($invite->organization_id === $this->organization->id, 404);

        $invite->delete();
        unset($this->invites);
    }

    public function updateRole(int $userId, string $role): void
    {
        $this->authorizeManage();
        $this->removalError = null;

        $user = User::findOrFail($userId);
        $actingRole = $this->myRole();
        $targetRole = $this->organization->roleFor($user);

        abort_unless($targetRole !== null, 404);
        abort_unless(in_array($role, ['owner', 'admin', 'member'], true), 422);

        if ($actingRole !== 'owner' && ($targetRole === 'owner' || $role === 'owner')) {
            abort(403);
        }

        if ($targetRole === 'owner' && $role !== 'owner'
            && $this->organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            $this->removalError = 'An organization needs at least one owner.';

            return;
        }

        $this->organization->users()->updateExistingPivot($userId, ['role' => $role]);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'member.role_updated', $user->email, [
            'from' => $targetRole,
            'to' => $role,
        ]);

        unset($this->members);
    }

    public function removeMember(int $userId): void
    {
        $this->authorizeManage();
        $this->removalError = null;

        $user = User::findOrFail($userId);
        $targetRole = $this->organization->roleFor($user);

        if ($targetRole === 'owner' && $this->organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            $this->removalError = 'An organization needs at least one owner.';

            return;
        }

        $this->organization->users()->detach($userId);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'member.removed', $user->email);

        unset($this->members);
    }

    public function render()
    {
        return view('livewire.members-manager');
    }
}
