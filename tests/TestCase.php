<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests use a dedicated Redis database (see phpunit.xml), but it isn't
        // reset between tests the way RefreshDatabase resets the database
        // connection. Flush it so leftover fault:issue:* counters (or anything
        // else keyed by an id that SQLite may reuse across tests) can't leak
        // from one test into another.
        Redis::connection()->flushdb();
    }
}
