<?php

namespace Tests\Feature\Console;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\SourceMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_it_deletes_empty_stale_issues_but_keeps_active_ones(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id, 'retention_days' => 30]);

        $stale = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'last_seen_at' => now()->subDays(60)]);
        $active = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'last_seen_at' => now()->subDay()]);
        $withRecentEvent = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'last_seen_at' => now()->subDays(60)]);

        FaultEvent::factory()->create(['fault_project_id' => $project->id, 'fault_issue_id' => $stale->id, 'occurred_at' => now()->subDays(60)]);
        FaultEvent::factory()->create(['fault_project_id' => $project->id, 'fault_issue_id' => $withRecentEvent->id, 'occurred_at' => now()->subDay()]);

        $this->artisan('zerrors:prune-events')->assertExitCode(0);

        $this->assertModelMissing($stale);
        $this->assertModelExists($active);
        $this->assertModelExists($withRecentEvent);
    }

    public function test_it_prunes_more_events_than_a_single_chunk(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id, 'retention_days' => 30]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        FaultEvent::factory()->count(12)->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(45),
        ]);

        $this->artisan('zerrors:prune-events')->expectsOutputToContain('12 event(s)')->assertExitCode(0);

        $this->assertSame(0, FaultEvent::where('fault_issue_id', $issue->id)->count());
    }

    public function test_it_prunes_old_source_maps_of_releases_without_events(): void
    {
        Storage::fake();
        $project = FaultProject::factory()->create(['retention_days' => 30]);
        $old = now()->subDays(60);

        $orphan = SourceMap::factory()->create(['fault_project_id' => $project->id, 'release' => '1.0.0', 'created_at' => $old]);
        $inUse = SourceMap::factory()->create(['fault_project_id' => $project->id, 'release' => '2.0.0', 'created_at' => $old]);
        $recent = SourceMap::factory()->create(['fault_project_id' => $project->id, 'release' => '3.0.0']);
        Storage::put($orphan->path, '{}');
        FaultEvent::factory()->create(['fault_project_id' => $project->id, 'release' => '2.0.0']);

        $this->artisan('zerrors:prune-events')->assertSuccessful();

        $this->assertModelMissing($orphan);
        Storage::assertMissing($orphan->path);
        $this->assertModelExists($inUse);
        $this->assertModelExists($recent);
    }
}
