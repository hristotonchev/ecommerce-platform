<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessProductImage;

class ProductTest extends TestCase
{
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Storage::fake('public');

        $this->categoryId = DB::table('categories')->insertGetId([
            'name'       => 'Test Cat ' . uniqid(),
            'slug'       => 'test-cat-' . uniqid(),
            'created_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('products')
            ->where('category_id', $this->categoryId)
            ->orderBy('id')
            ->each(function($p) {
                DB::table('inventory')->where('product_id', $p->id)->delete();
            });
        DB::table('products')->where('category_id', $this->categoryId)->delete();
        DB::table('categories')->where('id', $this->categoryId)->delete();
        parent::tearDown();
    }

    public function test_guest_cannot_access_products(): void
    {
        $this->get('/admin/products')->assertRedirect('/login');
    }

    public function test_admin_can_view_products_list(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get('/admin/products')->assertStatus(200);
    }

    public function test_admin_can_see_create_product_form(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)->get('/admin/products/create')
            ->assertStatus(200)
            ->assertSee('Create Product');
    }

    public function test_create_product_validates_required_fields(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin)
            ->post('/admin/products', [])
            ->assertSessionHasErrors(['name', 'description', 'price', 'category_id', 'quantity']);
    }

    public function test_admin_can_create_product(): void
    {
        $admin      = $this->createAdminUser();
        $uniqueName = 'Test Product ' . uniqid();

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name'        => $uniqueName,
            'description' => 'Test description here',
            'price'       => 99.99,
            'category_id' => $this->categoryId,
            'quantity'    => 10,
            'is_active'   => 1,
        ]);

        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', ['name' => $uniqueName]);
    }

    public function test_admin_can_create_product_with_image(): void
    {
        $admin      = $this->createAdminUser();
        $uniqueName = 'Image Product ' . uniqid();
        $image      = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->actingAs($admin)->post('/admin/products', [
            'name'        => $uniqueName,
            'description' => 'Has image description',
            'price'       => 49.99,
            'category_id' => $this->categoryId,
            'quantity'    => 5,
            'image'       => $image,
        ]);

        $response->assertRedirect('/admin/products');

        // Verify job was dispatched to queue
        Bus::assertDispatched(ProcessProductImage::class);

        // Verify product was created
        $this->assertDatabaseHas('products', ['name' => $uniqueName]);
    }
}
