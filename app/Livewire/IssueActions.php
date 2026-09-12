<?php

namespace App\Livewire;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use App\Support\Fault\GithubCommitFetcher;
use App\Support\Fault\GithubIssueCreator;
use App\Support\Organization\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Component;
use RuntimeException;

class IssueActions extends Component
{
    public Organization $organization;

    public FaultProject $project;

    public FaultIssue $issue;

    public ?FaultEvent $event = null;

    public ?int $assignedToUserId = null;

    public ?string $githubError = null;

    public ?string $commitError = null;

    /** @var Collection<int, User> */
    public Collection $members;

    public function mount(Organization $organization, FaultProject $project, FaultIssue $issue, ?FaultEvent $event = null, ?Collection $members = null): void
    {
        $this->organization = $organization;
        $this->project = $project;
        $this->issue = $issue;
        $this->event = $event;
        $this->assignedToUserId = $issue->assigned_to_user_id;
        $this->members = $members ?? $organization->users()->orderBy('name')->get();
    }

    public function updateStatus(string $status): void
    {
        abort_unless(in_array($status, ['unresolved', 'resolved', 'ignored'], true), 422);

        $previousStatus = $this->issue->status;

        $this->issue->update(['status' => $status]);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'issue.status_updated', $this->issue->title, [
            'from' => $previousStatus,
            'to' => $status,
        ]);
    }

    public function updatedAssignedToUserId(?string $value): void
    {
        $userId = $value !== null && $value !== '' ? (int) $value : null;

        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            $this->addError('assignedToUserId', 'That user is not a member of this organization.');
            $this->assignedToUserId = $this->issue->assigned_to_user_id;

            return;
        }

        $this->issue->update(['assigned_to_user_id' => $userId]);
    }

    public function createGithubIssue(GithubIssueCreator $creator): void
    {
        $this->githubError = null;

        try {
            $creator->create($this->issue);
        } catch (RuntimeException $e) {
            $this->githubError = $e->getMessage();
        }
    }

    public function fetchCommit(GithubCommitFetcher $fetcher): void
    {
        $this->commitError = null;

        $release = $this->issue->linkedRelease();

        if (! $release || ! $release->hasCommit()) {
            $this->commitError = "No release with a linked commit was found for \"{$this->issue->first_seen_release}\".";

            return;
        }

        try {
            $fetcher->fetch($release);
        } catch (RuntimeException $e) {
            $this->commitError = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.issue-actions', [
            'members' => $this->members,
            'linkedRelease' => $this->issue->linkedRelease(),
        ]);
    }
}
