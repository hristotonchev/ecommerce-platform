@extends('layouts.admin')
@section('title', 'Import Products')
@section('content')
<div class="max-w-2xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Import Products</h1>
        <a href="{{ route('admin.products.index') }}" class="text-blue-600 hover:underline">← Back</a>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-blue-800 mb-2">CSV Format</h3>
        <p class="text-blue-700 text-sm mb-3">
            Your CSV file must have these columns in order:
        </p>
        <code class="bg-white px-3 py-1 rounded text-sm border">
            name, description, price, category_id, quantity
        </code>
        <div class="mt-3">
            <a href="{{ route('admin.products.import.template') }}"
               class="text-blue-600 hover:underline text-sm font-medium">
                Download template CSV →
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.import.store') }}"
          enctype="multipart/form-data"
          class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-2">CSV File</label>
            <input type="file" name="file" accept=".csv,.txt" required
                   class="border rounded px-3 py-2 w-full @error('file') border-red-500 @enderror">
            @error('file')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
            <p class="text-gray-500 text-xs mt-1">Max 10MB. CSV or TXT format.</p>
        </div>

        <div class="flex space-x-3">
            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Import Products
            </button>
            <a href="{{ route('admin.products.index') }}"
               class="px-6 py-2 border rounded hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
