<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enforce isolated test database to protect working databases
        Config::set('database.connections.mongodb.database', 'scholar9_test');
    }
}
