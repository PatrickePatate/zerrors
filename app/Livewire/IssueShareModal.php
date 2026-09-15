<?php

namespace App\Livewire;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class IssueShareModal extends Component
{
    public Organization $organization;

    public FaultProject $project;

    public FaultIssue $issue;

    public string $visibility = 'public';

    public ?string $password = null;

    public string $duration = '86400';

    public function mount(Organization $organization, FaultProject $project, FaultIssue $issue): void
    {
        $this->organization = $organization;
        $this->project = $project;
        $this->issue = $issue;
    }

    public function createLink(): void
    {
        $data = $this->validate([
            'visibility' => ['required', 'in:public,password,temporary'],
            'password' => ['required_if:visibility,password', 'nullable', 'string', 'min:4'],
            'duration' => ['required_if:visibility,temporary', 'in:3600,86400,604800,2592000'],
        ]);

        $link = $this->issue->shareLinks()->create([
            'created_by_user_id' => auth()->id(),
            'visibility' => $data['visibility'],
            'password_hash' => $data['visibility'] === 'password' ? Hash::make($data['password']) : null,
            'expires_at' => $data['visibility'] === 'temporary' ? now()->addSeconds((int) $data['duration']) : null,
        ]);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'issue.share_link_created', $this->issue->title, [
            'visibility' => $link->visibility,
        ]);

        $this->reset(['password']);
        $this->visibility = 'public';
    }

    public function revoke(int $linkId): void
    {
        $link = $this->issue->shareLinks()->findOrFail($linkId);
        $link->update(['revoked_at' => now()]);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'issue.share_link_revoked', $this->issue->title);
    }

    public function render()
    {
        return view('livewire.issue-share-modal', [
            'links' => $this->issue->shareLinks()->latest()->get(),
        ]);
    }
}
