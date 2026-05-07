<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('inventory as i', 'p.id', '=', 'i.product_id')
            ->select('p.*', 'c.name as category_name', 'i.quantity')
            ->whereNull('p.deleted_at')
            ->orderBy('p.created_at', 'desc');

        if ($request->search) {
            $query->where('p.name', 'ilike', '%' . $request->search . '%');
        }

        $products = $query->paginate(20);
        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $categories = DB::table('categories')->get();
        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity'    => 'required|integer|min:0',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $productId = DB::table('products')->insertGetId([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . time(),
            'description' => $request->description,
            'price'       => $request->price,
            'category_id' => $request->category_id,
            'image_path'  => $imagePath,
            'is_active'   => $request->has('is_active'),
            'created_at'  => now(),
        ]);

        DB::table('inventory')->insert([
            'product_id' => $productId,
            'quantity'   => $request->quantity,
            'reserved'   => 0,
            'updated_at' => now(),
        ]);

        $this->notifyNestjs($productId);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product "' . $request->name . '" created successfully!');
    }

    public function edit($id)
    {
        $product = DB::table('products as p')
            ->leftJoin('inventory as i', 'p.id', '=', 'i.product_id')
            ->select('p.*', 'i.quantity')
            ->where('p.id', $id)
            ->first();

        abort_if(!$product, 404);

        $categories = DB::table('categories')->get();
        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity'    => 'required|integer|min:0',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $data = [
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'category_id' => $request->category_id,
            'is_active'   => $request->has('is_active'),
        ];

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Delete old image
            $old = DB::table('products')->where('id', $id)->value('image_path');
            if ($old) Storage::disk('public')->delete($old);

            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        DB::table('products')->where('id', $id)->update($data);
        DB::table('inventory')->where('product_id', $id)
            ->update(['quantity' => $request->quantity, 'updated_at' => now()]);

        $this->notifyNestjs($id);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully!');
    }

    public function destroy($id)
    {
        DB::table('products')->where('id', $id)->update(['deleted_at' => now()]);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted!');
    }

    public function restore($id)
    {
        DB::table('products')->where('id', $id)->update(['deleted_at' => null]);
        return redirect()->route('admin.products.index')
            ->with('success', 'Product restored!');
    }

    private function notifyNestjs(int $productId): void
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 3]);
            $client->post(
                config('services.nestjs.url') . '/api/internal/cache/invalidate',
                [
                    'headers' => ['X-API-Key' => config('services.nestjs.api_key')],
                    'json'    => ['type' => 'product', 'id' => $productId],
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Nest.js notification failed: ' . $e->getMessage());
        }
    }
}
