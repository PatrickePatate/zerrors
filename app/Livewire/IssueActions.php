<?php

namespace App\Livewire;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Fault\GithubCommitFetcher;
use App\Support\Fault\GithubIssueCreator;
use Livewire\Component;
use RuntimeException;

class IssueActions extends Component
{
    public Organization $organization;

    public FaultProject $project;

    public FaultIssue $issue;

    public ?int $assignedToUserId = null;

    public ?string $githubError = null;

    public ?string $commitError = null;

    public function mount(Organization $organization, FaultProject $project, FaultIssue $issue): void
    {
        $this->organization = $organization;
        $this->project = $project;
        $this->issue = $issue;
        $this->assignedToUserId = $issue->assigned_to_user_id;
    }

    public function updateStatus(string $status): void
    {
        abort_unless(in_array($status, ['unresolved', 'resolved', 'ignored'], true), 422);

        $this->issue->update(['status' => $status]);
    }

    public function updatedAssignedToUserId(?string $value): void
    {
        $userId = $value !== '' ? (int) $value : null;

        if ($userId !== null) {
            abort_unless($this->organization->users()->where('user_id', $userId)->exists(), 422);
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
            'members' => $this->organization->users()->orderBy('name')->get(),
            'linkedRelease' => $this->issue->linkedRelease(),
        ]);
    }
}
