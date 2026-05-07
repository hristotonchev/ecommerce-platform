@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-bold mb-6">Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-gray-500 text-sm">Total Orders</div>
        <div class="text-3xl font-bold text-blue-600">{{ $stats['total_orders'] }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-gray-500 text-sm">Total Revenue</div>
        <div class="text-3xl font-bold text-green-600">${{ number_format($stats['total_revenue'], 2) }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-gray-500 text-sm">Total Products</div>
        <div class="text-3xl font-bold text-purple-600">{{ $stats['total_products'] }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-gray-500 text-sm">Pending Orders</div>
        <div class="text-3xl font-bold text-yellow-600">{{ $stats['pending_orders'] }}</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Recent Orders</h2>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left border-b">
                <th class="pb-2">ID</th>
                <th class="pb-2">Status</th>
                <th class="pb-2">Total</th>
                <th class="pb-2">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stats['recent_orders'] as $order)
            <tr class="border-b hover:bg-gray-50">
                <td class="py-2">#{{ $order->id }}</td>
                <td class="py-2">
                    <span class="px-2 py-1 rounded text-xs
                        {{ $order->status === 'confirmed' ? 'bg-green-100 text-green-800' : '' }}
                        {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        {{ $order->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}
                        {{ $order->status === 'shipped' ? 'bg-blue-100 text-blue-800' : '' }}
                    ">
                        {{ ucfirst($order->status) }}
                    </span>
                </td>
                <td class="py-2">${{ number_format($order->total_amount, 2) }}</td>
                <td class="py-2">{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
