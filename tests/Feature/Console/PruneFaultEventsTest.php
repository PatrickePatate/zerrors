<?php

namespace Tests\Feature\Console;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneFaultEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_events_older_than_the_projects_retention_window(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id, 'retention_days' => 30]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $old = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(45),
        ]);
        $recent = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(2),
        ]);

        $this->artisan('zerrors:prune-events')->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($issue);
    }

    public function test_it_skips_projects_with_unlimited_retention(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id, 'retention_days' => 0]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $old = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subYears(2),
        ]);

        $this->artisan('zerrors:prune-events');

        $this->assertModelExists($old);
    }
}
