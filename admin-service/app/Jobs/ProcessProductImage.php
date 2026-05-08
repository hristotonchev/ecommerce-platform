<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        private int    $productId,
        private string $imagePath,
    ) {}

    public function handle(): void
    {
        Log::info("[ImageQueue] Processing image for product #{$this->productId}");

        // TODO: Perform real image optimisation before saving:
        //         1. Resize to multiple break-points (e.g. 800px, 400px, 200px).
        //         2. Convert to WebP for modern browsers while keeping a JPEG fallback.
        //         3. Strip EXIF metadata to avoid leaking GPS coordinates.
        //         4. Upload all variants to S3 (or another object store) and store
        //            the CDN URLs instead of local disk paths.
        //       Use intervention/image (v3) which is already in composer.json.
        //
        // TODO: Store all generated variant paths in a `product_images` table
        //       rather than a single `image_path` column so the API can serve
        //       the right size based on the requesting device's viewport.

        $fullPath = Storage::disk('public')->path($this->imagePath);

        if (!file_exists($fullPath)) {
            Log::warning("[ImageQueue] Image not found: {$this->imagePath}");
            return;
        }

        $size = filesize($fullPath);
        Log::info("[ImageQueue] Image processed for product #{$this->productId} — size: {$size} bytes — path: {$this->imagePath}");

        // Update product with processed image path
        DB::table('products')
            ->where('id', $this->productId)
            ->update(['image_path' => $this->imagePath]);

        Log::info("[ImageQueue] Done for product #{$this->productId}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[ImageQueue] Failed for product #{$this->productId}: " . $e->getMessage());
    }
}
