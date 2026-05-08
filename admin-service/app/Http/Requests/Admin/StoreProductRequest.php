<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'price'       => 'required|numeric|min:0.01|max:999999',
            'category_id' => 'required|integer|exists:categories,id',
            'quantity'    => 'required|integer|min:0|max:99999',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'is_active'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Product name is required.',
            'description.min'      => 'Description must be at least 10 characters.',
            'price.min'            => 'Price must be greater than 0.',
            'category_id.exists'   => 'Selected category does not exist.',
            'quantity.min'         => 'Quantity cannot be negative.',
            'image.mimes'          => 'Image must be jpeg, png, jpg, gif or webp.',
            'image.max'            => 'Image size cannot exceed 10MB.',
        ];
    }
}
