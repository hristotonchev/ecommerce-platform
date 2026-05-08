<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'price'       => (float) $this->price,
            'image_url'   => $this->image_path
                                ? asset('storage/' . $this->image_path)
                                : null,
            'is_active'   => (bool) $this->is_active,
            'category'    => $this->category_name ?? null,
            'category_id' => $this->category_id,
            'stock'       => [
                'quantity' => (int) ($this->quantity ?? 0),
                'available'=> (int) (($this->quantity ?? 0) - ($this->reserved ?? 0)),
            ],
            'created_at'  => $this->created_at,
            'deleted_at'  => $this->deleted_at,
        ];
    }
}
