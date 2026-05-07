<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class ReportTest extends TestCase
{
    public function test_guest_cannot_access_reports(): void
    {
        $this->get('/admin/reports')->assertRedirect('/login');
    }

    public function test_admin_can_view_reports(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get('/admin/reports')
            ->assertStatus(200)
            ->assertSee('Sales Reports')
            ->assertSee('Top 10 Products')
            ->assertSee('Daily Sales')
            ->assertSee('Monthly Sales');
    }

    public function test_admin_can_export_daily_csv(): void
    {
        $admin = $this->createAdminUser();
        $response = $this->actingAs($admin)
            ->get('/admin/reports/export?period=daily');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'text/csv',
            $response->headers->get('Content-Type')
        );
    }

    public function test_admin_can_export_monthly_csv(): void
    {
        $admin = $this->createAdminUser();
        $response = $this->actingAs($admin)
            ->get('/admin/reports/export?period=monthly');

        $response->assertStatus(200);
    }

    public function test_csv_contains_correct_headers(): void
    {
        $admin = $this->createAdminUser();
        $content = $this->actingAs($admin)
            ->get('/admin/reports/export?period=monthly')
            ->streamedContent();

        $this->assertStringContainsString('Period', $content);
        $this->assertStringContainsString('Total Orders', $content);
        $this->assertStringContainsString('Total Revenue', $content);
    }
}
