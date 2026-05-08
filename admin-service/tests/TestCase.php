<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

abstract class TestCase extends BaseTestCase
{
    // RefreshDatabase runs all pending migrations before the test suite starts
    // and wraps every test method in a database transaction that is rolled back
    // afterwards — so each test starts with a clean, fully-migrated schema.
    // This removes the dependency on a pre-run `php artisan migrate` step in CI.
    use RefreshDatabase;

    protected function createAdminUser(): User
    {
        return User::factory()->make([
            'id'   => rand(1000, 9999),
            'role' => 'admin',
            'name' => 'Test Admin',
        ]);
    }

    protected function createCustomerUser(): User
    {
        return User::factory()->make([
            'id'   => rand(1000, 9999),
            'role' => 'customer',
            'name' => 'Test Customer',
        ]);
    }
}
