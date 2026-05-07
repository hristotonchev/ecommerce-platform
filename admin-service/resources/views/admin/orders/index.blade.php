@extends('layouts.admin')
@section('title', 'Orders')
@section('content')
<h1 class="text-2xl font-bold mb-6">Orders</h1>

<form method="GET" class="mb-4 flex space-x-3">
    <input name="search" value="{{ request('search') }}" placeholder="Search by email..."
           class="border rounded px-3 py-2 w-64">
    <select name="status" class="border rounded px-3 py-2">
        <option value="">All statuses</option>
        @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                {{ ucfirst($s) }}
            </option>
        @endforeach
    </select>
    <button class="bg-gray-600 text-white px-4 py-2 rounded">Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-left">Total</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($orders as $order)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">#{{ $order->id }}</td>
                <td class="px-4 py-3">
                    <div>{{ $order->user_name }}</div>
                    <div class="text-gray-500 text-xs">{{ $order->user_email }}</div>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded text-xs
                        {{ $order->status === 'confirmed'  ? 'bg-green-100 text-green-800'  : '' }}
                        {{ $order->status === 'pending'    ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $order->status === 'cancelled'  ? 'bg-red-100 text-red-800'    : '' }}
                        {{ $order->status === 'shipped'    ? 'bg-blue-100 text-blue-800'   : '' }}
                        {{ $order->status === 'delivered'  ? 'bg-purple-100 text-purple-800' : '' }}
                    ">{{ ucfirst($order->status) }}</span>
                </td>
                <td class="px-4 py-3">${{ number_format($order->total_amount, 2) }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y') }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $order->id) }}"
                       class="text-blue-600 hover:underline">View</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
