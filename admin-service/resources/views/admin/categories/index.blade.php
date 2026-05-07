@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Categories</h1>
    <a href="{{ route('admin.categories.create') }}"
       class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        + New Category
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-left">Parent</th>
                <th class="px-4 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($categories as $category)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">{{ $category->id }}</td>
                <td class="px-4 py-3 font-medium">
                    {{ $category->parent_id ? '↳ ' : '' }}{{ $category->name }}
                </td>
                <td class="px-4 py-3">{{ $category->parent_name ?? '—' }}</td>
                <td class="px-4 py-3 space-x-2">
                    <a href="{{ route('admin.categories.edit', $category->id) }}"
                       class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST"
                          action="{{ route('admin.categories.destroy', $category->id) }}"
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
@endsection
