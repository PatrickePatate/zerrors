<?php

namespace Tests\Feature\Console;

use App\Mail\OrganizationDigestMail;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDigestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_only_to_opted_in_members_for_the_given_frequency(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'first_seen_at' => now()->subHours(2)]);
        FaultEvent::factory()->count(3)->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subHours(2),
        ]);

        $daily = User::factory()->create();
        $weekly = User::factory()->create();
        $none = User::factory()->create();
        $unset = User::factory()->create();
        $organization->users()->attach($daily->id, ['role' => 'member', 'digest_frequency' => 'daily']);
        $organization->users()->attach($weekly->id, ['role' => 'member', 'digest_frequency' => 'weekly']);
        $organization->users()->attach($none->id, ['role' => 'member', 'digest_frequency' => 'none']);
        $organization->users()->attach($unset->id, ['role' => 'member']);

        $this->artisan('zerrors:send-digests', ['frequency' => 'daily'])->assertSuccessful();

        Mail::assertQueued(OrganizationDigestMail::class, 1);
        Mail::assertQueued(OrganizationDigestMail::class, fn ($mail) => $mail->hasTo($daily->email)
            && $mail->projects[0]['events'] === 3
            && $mail->projects[0]['new_issues'] === 1
            && $mail->projects[0]['top_issues'][0]['count'] === 3);
    }

    public function test_it_skips_organizations_with_nothing_to_report(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'first_seen_at' => now()->subDays(30)]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(30),
        ]);
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member', 'digest_frequency' => 'weekly']);

        $this->artisan('zerrors:send-digests', ['frequency' => 'weekly'])->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_it_rejects_an_unknown_frequency(): void
    {
        $this->artisan('zerrors:send-digests', ['frequency' => 'hourly'])->assertFailed();
    }
}
