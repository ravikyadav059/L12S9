<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enforce isolated test database and isolated cache to protect working databases and live Redis cache
        Config::set('database.connections.mongodb.database', 'scholar9_test');
        Config::set('cache.default', 'array');
        Config::set('cache.prefix', 'testing_cache_');
    }
}
