<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class OrderTest extends TestCase
{
    private int $orderId;
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerId = DB::table('users')->insertGetId([
            'name'       => 'Order Customer ' . uniqid(),
            'email'      => 'order-test-' . uniqid() . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'customer',
            'created_at' => now(),
        ]);

        $this->orderId = DB::table('orders')->insertGetId([
            'user_id'      => $this->customerId,
            'status'       => 'confirmed',
            'total_amount' => 999.99,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('orders')->where('id', $this->orderId)->delete();
        DB::table('users')->where('id', $this->customerId)->delete();
        parent::tearDown();
    }

    public function test_guest_cannot_access_orders(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
    }

    public function test_admin_can_view_orders_list(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get('/admin/orders')
            ->assertStatus(200)
            ->assertSee('Orders');
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get('/admin/orders?status=confirmed')
            ->assertStatus(200);
    }

    public function test_admin_can_view_single_order(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get("/admin/orders/{$this->orderId}")
            ->assertStatus(200)
            ->assertSee('Order #');
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)
            ->put("/admin/orders/{$this->orderId}", ['status' => 'shipped'])
            ->assertRedirect("/admin/orders/{$this->orderId}");

        $order = DB::table('orders')->where('id', $this->orderId)->first();
        $this->assertEquals('shipped', $order->status);
    }

    public function test_order_status_validates_allowed_values(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)
            ->put("/admin/orders/{$this->orderId}", ['status' => 'invalid'])
            ->assertSessionHasErrors(['status']);
    }
}
