<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;

abstract class TestCase extends BaseTestCase
{
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
