<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class IngestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_is_rate_limited_per_project(): void
    {
        $project = FaultProject::factory()->create();

        RateLimiter::for('fault-ingest', fn () => Limit::perMinute(1)->by($project->id));

        $this->postJson("/api/{$project->id}/store/", [])->assertStatus(401);
        $this->postJson("/api/{$project->id}/store/", [])->assertStatus(429);
    }
}
