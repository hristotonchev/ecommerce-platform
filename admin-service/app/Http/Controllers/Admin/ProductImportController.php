<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    public function form()
    {
        return view('admin.products.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $header  = fgetcsv($handle); // Skip header row

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            try {
                // Expected columns: name, description, price, category_id, quantity
                if (count($line) < 5) {
                    $errors[] = "Row {$row}: Not enough columns";
                    $skipped++;
                    continue;
                }

                [$name, $description, $price, $category_id, $quantity] = $line;

                if (empty($name) || !is_numeric($price) || !is_numeric($quantity)) {
                    $errors[] = "Row {$row}: Invalid data — name={$name} price={$price} qty={$quantity}";
                    $skipped++;
                    continue;
                }

                // Check category exists
                $category = DB::table('categories')->where('id', (int)$category_id)->first();
                if (!$category) {
                    $errors[] = "Row {$row}: Category #{$category_id} not found";
                    $skipped++;
                    continue;
                }

                $slug = Str::slug($name) . '-' . time() . '-' . $row;

                $productId = DB::table('products')->insertGetId([
                    'name'        => trim($name),
                    'slug'        => $slug,
                    'description' => trim($description),
                    'price'       => (float) $price,
                    'category_id' => (int) $category_id,
                    'is_active'   => true,
                    'created_at'  => now(),
                ]);

                DB::table('inventory')->insert([
                    'product_id'  => $productId,
                    'quantity'    => (int) $quantity,
                    'reserved'    => 0,
                    'updated_at'  => now(),
                ]);

                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Row {$row}: " . $e->getMessage();
                $skipped++;
            }
        }

        fclose($handle);

        $message = "Import complete: {$imported} imported, {$skipped} skipped.";
        if (!empty($errors)) {
            $message .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
        }

        return redirect()->route('admin.products.index')
            ->with($skipped > 0 && $imported === 0 ? 'error' : 'success', $message);
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products-import-template.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['name', 'description', 'price', 'category_id', 'quantity']);
            fputcsv($file, ['iPhone 16', 'Latest Apple smartphone', '1099.99', '4', '30']);
            fputcsv($file, ['Samsung S25', 'Android flagship', '899.99', '4', '25']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
