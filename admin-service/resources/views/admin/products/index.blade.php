@extends('layouts.admin')
@section('title', 'Products')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Products</h1>
    <a href="{{ route('admin.products.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        + New Product</a>
        <a href="{{ route('admin.products.import') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Import CSV
    </a>
</div>

<form method="GET" class="mb-4">
    <input name="search" value="{{ request('search') }}"
           placeholder="Search products..."
           class="border rounded px-3 py-2 w-64">
    <button class="bg-gray-600 text-white px-4 py-2 rounded ml-2">Search</button>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Category</th>
                <th class="px-4 py-3 text-left">Price</th>
                <th class="px-4 py-3 text-left">Stock</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($products as $product)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ $product->id }}</td>
                <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                <td class="px-4 py-3">{{ $product->category_name }}</td>
                <td class="px-4 py-3">${{ number_format($product->price, 2) }}</td>
                <td class="px-4 py-3">{{ $product->quantity ?? 0 }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded text-xs {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-4 py-3 space-x-2">
                    <a href="{{ route('admin.products.edit', $product->id) }}"
                       class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}"
                          class="inline" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:underline">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
