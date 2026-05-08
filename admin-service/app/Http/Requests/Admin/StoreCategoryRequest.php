<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255|min:2',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Category name is required.',
            'name.min'         => 'Category name must be at least 2 characters.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ];
    }
}
