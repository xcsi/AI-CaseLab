<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles are essential reference data every test that touches a User
     * depends on, so seed automatically whenever RefreshDatabase migrates.
     */
    protected $seed = true;
}
