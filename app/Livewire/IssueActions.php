<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use App\Support\Fault\GithubCommitFetcher;
use App\Support\Fault\GithubIssueCreator;
use App\Support\Fault\IssueWorkflow;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class IssueActions extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public Organization $organization;

    #[Locked]
    public FaultProject $project;

    #[Locked]
    public FaultIssue $issue;

    #[Locked]
    public ?FaultEvent $event = null;

    public ?int $assignedToUserId = null;

    public ?string $githubError = null;

    public ?string $commitError = null;

    /** @var Collection<int, User> */
    #[Locked]
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

    public function updateStatus(string $status, ?string $option = null): void
    {
        abort_unless(in_array($status, ['unresolved', 'resolved', 'ignored'], true), 422);
        $this->authorizeMember();

        $workflow = app(IssueWorkflow::class);
        $user = auth()->user();

        match ($status) {
            'resolved' => $workflow->resolve($this->organization, $this->issue, $user, $option ?? 'now'),
            'ignored' => $workflow->ignore($this->organization, $this->issue, $user, $option ?? 'forever'),
            'unresolved' => $workflow->unresolve($this->organization, $this->issue, $user),
        };

        $this->issue->refresh();
        $this->dispatch('issue-status-updated', issueId: $this->issue->id);
    }

    public function updatedAssignedToUserId(?string $value): void
    {
        $this->authorizeMember();
        $userId = $value !== null && $value !== '' ? (int) $value : null;

        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            $this->addError('assignedToUserId', 'That user is not a member of this organization.');
            $this->assignedToUserId = $this->issue->assigned_to_user_id;

            return;
        }

        app(IssueWorkflow::class)->assign($this->organization, $this->issue, auth()->user(), $userId);
    }

    protected function authorizeMember(): void
    {
        $this->authorizeWrite($this->organization);
        abort_unless($this->issue->fault_project_id === $this->project->id && $this->project->organization_id === $this->organization->id, 404);
    }

    public function createGithubIssue(GithubIssueCreator $creator): void
    {
        $this->githubError = null;

        $this->authorizeMember();

        try {
            $creator->create($this->issue);
            FaultIssueActivity::record($this->issue, 'github_linked', ['number' => $this->issue->github_issue_number, 'url' => $this->issue->github_issue_url], auth()->user());
        } catch (RuntimeException $e) {
            $this->githubError = $e->getMessage();
        }
    }

    public function fetchCommit(GithubCommitFetcher $fetcher): void
    {
        $this->authorizeView($this->organization);

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
            'hasReleases' => $this->project->releases()->exists(),
        ]);
    }
}
