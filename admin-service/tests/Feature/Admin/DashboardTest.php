<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_customer_cannot_access_dashboard(): void
    {
        $customer = $this->createCustomerUser();
        $response = $this->actingAs($customer)->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = $this->createAdminUser();
        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }
}
