<?php

namespace Tests\Feature\Organization;

use App\Enums\OrganizationRole;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Livewire\IssueActions;
use App\Livewire\IssueAnalysis;
use App\Livewire\IssueShareModal;
use App\Livewire\IssueTimeline;
use App\Livewire\IssueTriage;
use App\Livewire\MembersManager;
use App\Livewire\NotificationChannelManager;
use App\Livewire\OrganizationAiSettings;
use App\Livewire\OrganizationGeneralSettings;
use App\Livewire\ProjectCreationWizard;
use App\Livewire\ProjectIssueList;
use App\Livewire\RecentIssueList;
use App\Livewire\StatusPageManager;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Monitor;
use App\Models\Organization;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ViewerRoleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private FaultProject $project;

    private FaultIssue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->project = FaultProject::factory()->create(['organization_id' => $this->organization->id]);
        $this->issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'status' => 'unresolved']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->organization->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    public function test_ability_matrix(): void
    {
        $expected = [
            'owner' => ['view' => true, 'write' => true, 'manage' => true, 'own' => true],
            'admin' => ['view' => true, 'write' => true, 'manage' => true, 'own' => false],
            'member' => ['view' => true, 'write' => true, 'manage' => false, 'own' => false],
            'viewer' => ['view' => true, 'write' => false, 'manage' => false, 'own' => false],
        ];

        foreach ($expected as $role => $abilities) {
            $user = $this->userWithRole($role);
            $this->assertSame(OrganizationRole::from($role), $this->organization->roleEnumFor($user));

            foreach ($abilities as $ability => $allowed) {
                $this->assertSame($allowed, Gate::forUser($user)->allows($ability, $this->organization), "{$role} / {$ability}");
            }
        }

        $stranger = User::factory()->create();
        foreach (['view', 'write', 'manage', 'own'] as $ability) {
            $this->assertFalse(Gate::forUser($stranger)->allows($ability, $this->organization));
        }
    }

    public function test_viewer_can_read_org_pages(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(route('organizations.projects.index', $this->organization))->assertOk();
        $this->actingAs($viewer)->get(route('organizations.projects.show', [$this->organization, $this->project]))->assertOk();
        $this->actingAs($viewer)->get(route('organizations.issues.show', [$this->organization, $this->project, $this->issue]))->assertOk();
        $this->actingAs($viewer)->get(route('organizations.members.index', $this->organization))->assertOk();
    }

    public function test_every_mutating_org_route_is_forbidden_for_viewers(): void
    {
        $viewer = $this->userWithRole('viewer');
        $other = $this->userWithRole('member');

        $replacements = [
            'organization' => $this->organization->slug,
            'project' => $this->project->slug,
            'issue' => $this->issue->id,
            'user' => $other->id,
            'event' => 1,
            'release' => Release::factory()->create(['fault_project_id' => $this->project->id])->id,
            'monitor' => Monitor::factory()->create(['organization_id' => $this->organization->id])->id,
            'invite' => $this->organization->invites()->create(['email' => 'p@example.com', 'role' => 'member', 'invited_by' => $other->id])->id,
            'attachment' => 1,
        ];

        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            /** @var LaravelRoute $route */
            if (! in_array('org.member', $route->gatherMiddleware(), true)) {
                continue;
            }

            if (in_array($route->getName(), EnsureOrganizationMembership::VIEWER_ALLOWED_ROUTES, true)) {
                continue;
            }

            $uri = '/'.ltrim(preg_replace_callback('/\{(\w+)(?::\w+)?\}/', fn (array $m) => $replacements[$m[1]] ?? '1', $route->uri()), '/');

            foreach (array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) as $method) {
                $this->actingAs($viewer)->call($method, $uri)->assertForbidden();
                $checked++;
            }
        }

        $this->assertGreaterThan(15, $checked);
        $this->assertSame('unresolved', $this->issue->fresh()->status);
    }

    public function test_viewer_can_still_leave_the_organization(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->post(route('organizations.leave', $this->organization))->assertRedirect();

        $this->assertNull($this->organization->fresh()->roleFor($viewer));
    }

    public function test_viewer_livewire_issue_actions_are_forbidden(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(IssueActions::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $this->issue])
            ->call('updateStatus', 'resolved')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(IssueActions::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $this->issue])
            ->set('assignedToUserId', $viewer->id)
            ->assertForbidden();

        $this->assertSame('unresolved', $this->issue->fresh()->status);
        $this->assertNull($this->issue->fresh()->assigned_to_user_id);
    }

    public function test_viewer_livewire_issue_lists_are_forbidden(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->set('selected', [(string) $this->issue->id])
            ->call('bulkResolve')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->call('updateIssueStatus', $this->issue->id, 'resolved')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(RecentIssueList::class, ['organization' => $this->organization])
            ->call('updateIssueStatus', $this->issue->id, 'resolved')
            ->assertForbidden();

        $this->assertSame('unresolved', $this->issue->fresh()->status);
    }

    public function test_viewer_cannot_comment_share_analyze_or_triage(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(IssueTimeline::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $this->issue])
            ->set('body', 'hello')
            ->call('addComment')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(IssueShareModal::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $this->issue])
            ->call('createLink')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(IssueAnalysis::class, ['issue' => $this->issue])
            ->call('analyze')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(IssueTriage::class, ['issue' => $this->issue])
            ->call('rerun')
            ->assertForbidden();

        $this->assertSame(0, $this->issue->activities()->count());
        $this->assertSame(0, $this->issue->shareLinks()->count());
    }

    public function test_viewer_cannot_create_projects_via_wizard(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(ProjectCreationWizard::class, ['organization' => $this->organization])
            ->set('name', 'Nope')
            ->call('createProject')
            ->assertForbidden();

        $this->assertSame(1, $this->organization->projects()->count());
    }

    public function test_viewer_cannot_mount_or_use_manage_components(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(NotificationChannelManager::class, ['organization' => $this->organization, 'project' => $this->project])
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(StatusPageManager::class, ['organization' => $this->organization])
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(OrganizationGeneralSettings::class, ['organization' => $this->organization])
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(OrganizationAiSettings::class, ['organization' => $this->organization])
            ->call('disconnect')
            ->assertForbidden();
    }

    public function test_viewer_cannot_manage_members(): void
    {
        $viewer = $this->userWithRole('viewer');
        $member = $this->userWithRole('member');

        $mount = fn () => Livewire::actingAs($viewer)->test(MembersManager::class, ['organization' => $this->organization]);

        $mount()->call('updateRole', $member->id, 'admin')->assertForbidden();
        $mount()->call('removeMember', $member->id)->assertForbidden();
        $mount()->set('email', 'x@example.com')->call('invite')->assertForbidden();

        $this->assertSame('member', $this->organization->fresh()->roleFor($member));
    }

    public function test_viewer_api_can_read_but_not_write(): void
    {
        $viewer = $this->userWithRole('viewer');
        Sanctum::actingAs($viewer, ['read', 'write']);

        $base = '/api/v1/organizations/'.$this->organization->slug.'/projects/'.$this->project->slug;

        $this->getJson($base.'/issues')->assertOk();
        $this->getJson($base.'/issues/'.$this->issue->id)->assertOk();
        $this->getJson($base.'/releases')->assertOk();

        $this->patchJson($base.'/issues/'.$this->issue->id, ['status' => 'resolved'])->assertForbidden();
        $this->postJson($base.'/releases', ['version' => '1.0.0'])->assertForbidden();

        $this->assertSame('unresolved', $this->issue->fresh()->status);
    }

    public function test_member_and_admin_api_issue_update_records_activity(): void
    {
        foreach (['member', 'admin', 'owner'] as $role) {
            $user = $this->userWithRole($role);
            Sanctum::actingAs($user, ['read', 'write']);
            $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'status' => 'unresolved']);

            $this->patchJson('/api/v1/organizations/'.$this->organization->slug.'/projects/'.$this->project->slug.'/issues/'.$issue->id, ['status' => 'resolved'])
                ->assertOk();

            $this->assertSame('resolved', $issue->fresh()->status);
            $this->assertSame(1, $issue->activities()->where('type', 'status')->count());
        }
    }

    public function test_unchanged_member_and_admin_behaviour_on_web_and_livewire(): void
    {
        $member = $this->userWithRole('member');
        $admin = $this->userWithRole('admin');

        $this->actingAs($member)->patch(route('organizations.issues.update', [$this->organization, $this->project, $this->issue]), ['status' => 'resolved'])->assertRedirect();
        $this->assertSame('resolved', $this->issue->fresh()->status);

        $this->actingAs($member)->post(route('organizations.projects.store', $this->organization), ['name' => 'P2', 'platform' => 'javascript'])->assertRedirect();
        $this->actingAs($member)->patch(route('organizations.projects.settings.update', [$this->organization, $this->project]), [])->assertForbidden();
        $this->actingAs($member)->get(route('organizations.settings.edit', $this->organization))->assertOk();
        $this->actingAs($member)->delete(route('organizations.settings.destroy', $this->organization))->assertForbidden();
        $this->actingAs($admin)->delete(route('organizations.settings.destroy', $this->organization))->assertForbidden();

        Livewire::actingAs($member)
            ->test(IssueTimeline::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $this->issue])
            ->set('body', 'works')
            ->call('addComment')
            ->assertOk();

        Livewire::actingAs($member)
            ->test(NotificationChannelManager::class, ['organization' => $this->organization, 'project' => $this->project])
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(StatusPageManager::class, ['organization' => $this->organization])
            ->assertOk();
    }

    public function test_viewer_can_be_invited_and_role_changed_to_viewer(): void
    {
        $owner = $this->userWithRole('owner');
        $member = $this->userWithRole('member');

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->set('email', 'new@example.com')
            ->set('role', 'viewer')
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame('viewer', $this->organization->invites()->where('email', 'new@example.com')->value('role'));

        $this->actingAs($owner)->post(route('organizations.invites.store', $this->organization), ['email' => 'two@example.com', 'role' => 'viewer'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('organizations.invites.store', $this->organization), ['email' => 'three@example.com', 'role' => 'owner'])->assertSessionHasErrors('role');

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->call('updateRole', $member->id, 'viewer');

        $this->assertSame('viewer', $this->organization->fresh()->roleFor($member));

        $this->actingAs($owner)->patch(route('organizations.members.updateRole', [$this->organization, $member]), ['role' => 'member'])->assertRedirect();
        $this->assertSame('member', $this->organization->fresh()->roleFor($member));
    }

    public function test_admin_cannot_grant_owner_but_can_set_viewer(): void
    {
        $admin = $this->userWithRole('admin');
        $member = $this->userWithRole('member');

        Livewire::actingAs($admin)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->call('updateRole', $member->id, 'owner')
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->call('updateRole', $member->id, 'viewer');

        $this->assertSame('viewer', $this->organization->fresh()->roleFor($member));
    }

    public function test_accepting_an_invite_as_viewer_grants_the_viewer_role(): void
    {
        $owner = $this->userWithRole('owner');
        $invite = $this->organization->invites()->create(['email' => 'v@example.com', 'role' => 'viewer', 'invited_by' => $owner->id]);
        $invitee = User::factory()->create(['email' => 'v@example.com']);

        $this->actingAs($invitee)->get(route('invites.accept', $invite->token))->assertRedirect();

        $this->assertSame('viewer', $this->organization->fresh()->roleFor($invitee));
    }

    public function test_every_role_can_set_their_own_digest_frequency_only(): void
    {
        foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
            $user = $this->userWithRole($role);
            $other = $this->userWithRole('member');

            Livewire::actingAs($user)
                ->test(MembersManager::class, ['organization' => $this->organization])
                ->assertSet('digestFrequency', 'none')
                ->call('updateDigestFrequency', 'weekly')
                ->assertSet('digestFrequency', 'weekly');

            $pivot = fn (User $u) => $this->organization->users()->where('user_id', $u->id)->first()->pivot->digest_frequency;

            $this->assertSame('weekly', $pivot($user), $role);
            $this->assertNull($pivot($other), $role);
        }
    }

    public function test_digest_frequency_is_validated_and_members_only(): void
    {
        $viewer = $this->userWithRole('viewer');

        Livewire::actingAs($viewer)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->call('updateDigestFrequency', 'hourly')
            ->assertStatus(422);

        $stranger = User::factory()->create();

        Livewire::actingAs($stranger)
            ->test(MembersManager::class, ['organization' => $this->organization])
            ->call('updateDigestFrequency', 'daily')
            ->assertForbidden();
    }
}
