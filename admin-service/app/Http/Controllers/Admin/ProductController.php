<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Jobs\ProcessProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('inventory as i', 'p.id', '=', 'i.product_id')
            ->select('p.*', 'c.name as category_name', 'i.quantity', 'i.reserved')
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

    public function store(StoreProductRequest $request)
    {
        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // Store immediately
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

        // Dispatch image processing job to queue
        if ($imagePath) {
            ProcessProductImage::dispatch($productId, $imagePath);
        }

        $this->notifyNestjs($productId);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product "' . $request->name . '" created!');
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

    public function update(UpdateProductRequest $request, $id)
    {
        $data = [
            'name'        => $request->name,
            'description' => $request->description,
            'price'       => $request->price,
            'category_id' => $request->category_id,
            'is_active'   => $request->has('is_active'),
        ];

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $old = DB::table('products')->where('id', $id)->value('image_path');
            if ($old) Storage::disk('public')->delete($old);

            $imagePath       = $request->file('image')->store('products', 'public');
            $data['image_path'] = $imagePath;

            // Dispatch image processing job
            ProcessProductImage::dispatch($id, $imagePath);
        }

        DB::table('products')->where('id', $id)->update($data);
        DB::table('inventory')->where('product_id', $id)
            ->update(['quantity' => $request->quantity, 'updated_at' => now()]);

        $this->notifyNestjs($id);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated!');
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
        // TODO: Move this HTTP call into a dedicated NestjsWebhookService that is
        //       injected via the service container.  Benefits:
        //         - Testable in isolation (mock the service in feature tests).
        //         - Single place to add retry logic (e.g. a queued job with
        //           exponential back-off if Nest.js is temporarily unavailable).
        //         - Controllers stay thin.
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

    public function export()
    {
        $products = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('inventory as i', 'p.id', '=', 'i.product_id')
            ->select('p.id', 'p.name', 'p.description', 'p.price',
                     'c.name as category', 'i.quantity', 'p.is_active', 'p.created_at')
            ->whereNull('p.deleted_at')
            ->orderBy('p.id')
            ->get();

        $filename = 'products-export-' . now()->format('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($products) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Description', 'Price', 'Category', 'Quantity', 'Active', 'Created At']);
            foreach ($products as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->name,
                    $p->description,
                    $p->price,
                    $p->category ?? 'N/A',
                    $p->quantity ?? 0,
                    $p->is_active ? 'Yes' : 'No',
                    $p->created_at,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
