<?php

namespace App\Livewire;

use App\Enums\OrganizationRole;
use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use App\Support\Organization\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MembersManager extends Component
{
    use AuthorizesOrganizationAccess;
    use WithPagination;

    #[Locked]
    public Organization $organization;

    public string $email = '';

    public string $role = 'member';

    public ?string $inviteLink = null;

    public ?string $removalError = null;

    public bool $showInviteModal = false;

    public string $digestFrequency = 'none';

    /**
     * Digest frequencies a member can choose for themselves.
     *
     * @var list<string>
     */
    public const DIGEST_FREQUENCIES = ['none', 'daily', 'weekly'];

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->digestFrequency = $this->ownDigestFrequency();
    }

    protected function ownDigestFrequency(): string
    {
        $frequency = $this->organization->users()->where('user_id', auth()->id())->first()?->pivot->digest_frequency;

        return in_array($frequency, self::DIGEST_FREQUENCIES, true) ? $frequency : 'none';
    }

    /**
     * Per-user preference: any member (viewers included) may change their own
     * digest, never anyone else's.
     */
    public function updateDigestFrequency(string $frequency): void
    {
        $this->authorizeView($this->organization);
        abort_unless(in_array($frequency, self::DIGEST_FREQUENCIES, true), 422);

        $this->organization->users()->updateExistingPivot(auth()->id(), ['digest_frequency' => $frequency]);
        $this->digestFrequency = $frequency;
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

    public function invite(): void
    {
        $this->authorizeManage($this->organization);

        $data = $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(OrganizationRole::invitable())],
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
        $this->authorizeManage($this->organization);

        $invite = OrganizationInvite::findOrFail($inviteId);
        abort_unless($invite->organization_id === $this->organization->id, 404);

        $invite->delete();
        unset($this->invites);
    }

    public function updateRole(int $userId, string $role): void
    {
        $this->authorizeManage($this->organization);
        $this->removalError = null;

        $user = User::findOrFail($userId);
        $targetRole = $this->organization->roleFor($user);

        abort_unless($targetRole !== null, 404);
        abort_unless(OrganizationRole::tryFrom($role) !== null, 422);

        if (! Gate::allows('own', $this->organization) && ($targetRole === 'owner' || $role === 'owner')) {
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
        $this->authorizeManage($this->organization);
        $this->removalError = null;

        $user = User::findOrFail($userId);
        $targetRole = $this->organization->roleFor($user);

        abort_unless($targetRole !== null, 404);
        abort_if($targetRole === 'owner' && ! Gate::allows('own', $this->organization), 403);

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
