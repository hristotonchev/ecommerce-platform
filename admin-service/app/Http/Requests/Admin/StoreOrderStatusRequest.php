<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid status. Allowed: pending, confirmed, processing, shipped, delivered, cancelled.',
        ];
    }
}
