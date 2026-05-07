@extends('layouts.admin')
@section('title', 'Edit Category')
@section('content')
<div class="max-w-lg">
    <h1 class="text-2xl font-bold mb-6">Edit Category</h1>
    <form method="POST" action="{{ route('admin.categories.update', $category->id) }}"
          class="bg-white rounded-lg shadow p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input name="name" value="{{ old('name', $category->name) }}" required
                   class="border rounded px-3 py-2 w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Parent Category</label>
            <select name="parent_id" class="border rounded px-3 py-2 w-full">
                <option value="">None</option>
                @foreach($parents as $parent)
                    <option value="{{ $parent->id }}"
                        {{ $category->parent_id == $parent->id ? 'selected' : '' }}>
                        {{ $parent->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex space-x-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Update
            </button>
            <a href="{{ route('admin.categories.index') }}" class="px-6 py-2 border rounded">Cancel</a>
        </div>
    </form>
</div>
@endsection
