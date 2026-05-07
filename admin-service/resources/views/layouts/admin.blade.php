<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Panel') - Ecommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-gray-800 text-white px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-6">
            <span class="font-bold text-lg">Ecommerce Admin</span>
            <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-300">Dashboard</a>
            <a href="{{ route('admin.products.index') }}" class="hover:text-gray-300">Products</a>
            <a href="{{ route('admin.categories.index') }}" class="hover:text-gray-300">Categories</a>
            <a href="{{ route('admin.orders.index') }}" class="hover:text-gray-300">Orders</a>
            <a href="{{ route('admin.users.index') }}" class="hover:text-gray-300">Users</a>
            <a href="{{ route('admin.reports.index') }}" class="hover:text-gray-300">Reports</a>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-gray-300">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="hover:text-gray-300">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        {{-- Success message --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        {{-- Error message --}}
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Please fix the following errors:</strong>
                <ul class="mt-2 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
