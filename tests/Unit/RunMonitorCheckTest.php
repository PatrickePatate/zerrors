<?php

namespace Tests\Unit;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class RunMonitorCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prevents_overlapping_runs_for_the_same_monitor(): void
    {
        $monitor = Monitor::factory()->create();

        $middleware = (new RunMonitorCheck($monitor))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame($monitor->id, $middleware[0]->key);
    }
}
