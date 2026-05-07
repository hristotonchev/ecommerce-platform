@extends('layouts.admin')
@section('title', 'Edit Product')
@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Edit Product #{{ $product->id }}</h1>

    <form method="POST" action="{{ route('admin.products.update', $product->id) }}"
          enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input name="name" value="{{ old('name', $product->name) }}" required
                   class="border rounded px-3 py-2 w-full">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <textarea name="description" rows="3" required
                      class="border rounded px-3 py-2 w-full">{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Price ($)</label>
                <input name="price" type="number" step="0.01"
                       value="{{ old('price', $product->price) }}" required
                       class="border rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Stock Quantity</label>
                <input name="quantity" type="number"
                       value="{{ old('quantity', $product->quantity) }}" required
                       class="border rounded px-3 py-2 w-full">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Category</label>
            <select name="category_id" required class="border rounded px-3 py-2 w-full">
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}"
                        {{ $product->category_id == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($product->image_path)
        <div>
            <label class="block text-sm font-medium mb-1">Current Image</label>
            <img src="{{ Storage::url($product->image_path) }}" class="h-20 rounded">
        </div>
        @endif

        <div>
            <label class="block text-sm font-medium mb-1">New Image (optional)</label>
            <input name="image" type="file" accept="image/*"
                   class="border rounded px-3 py-2 w-full">
        </div>

        <div class="flex items-center">
            <input name="is_active" type="checkbox" value="1" class="mr-2"
                   {{ $product->is_active ? 'checked' : '' }}>
            <label class="text-sm">Active</label>
        </div>

        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Update Product
            </button>
            <a href="{{ route('admin.products.index') }}" class="px-6 py-2 border rounded hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
