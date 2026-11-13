<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultProject;
use App\Models\Organization;
use Livewire\Attributes\Locked;
use Livewire\Component;

class IssueTimeline extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public Organization $organization;

    #[Locked]
    public FaultProject $project;

    #[Locked]
    public FaultIssue $issue;

    public string $body = '';

    public function mount(Organization $organization, FaultProject $project, FaultIssue $issue): void
    {
        $this->organization = $organization;
        $this->project = $project;
        $this->issue = $issue;
    }

    public function addComment(): void
    {
        $this->authorizeMember();

        $this->validate(['body' => ['required', 'string', 'max:5000']]);

        FaultIssueActivity::record($this->issue, 'comment', ['body' => trim($this->body)], auth()->user());

        $this->reset('body');
    }

    public function deleteComment(int $activityId): void
    {
        $this->authorizeMember();

        $comment = $this->issue->activities()
            ->where('type', 'comment')
            ->where('user_id', auth()->id())
            ->findOrFail($activityId);

        $comment->delete();
    }

    protected function authorizeMember(): void
    {
        $this->authorizeWrite($this->organization);
        abort_unless($this->issue->fault_project_id === $this->project->id && $this->project->organization_id === $this->organization->id, 404);
    }

    public function render()
    {
        return view('livewire.issue-timeline', [
            'activities' => $this->issue->activities()->with('user:id,name')->orderByDesc('id')->limit(100)->get(),
            'members' => $this->organization->users()->pluck('users.name', 'users.id'),
        ]);
    }
}
