@extends('layouts.admin')
@section('title', 'Sales Reports')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Sales Reports</h1>
    <div class="space-x-2">
        <a href="{{ route('admin.reports.export', ['period' => 'daily']) }}"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Export Daily CSV
        </a>
        <a href="{{ route('admin.reports.export', ['period' => 'monthly']) }}"
           class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Export Monthly CSV
        </a>
    </div>
</div>

{{-- Top Products --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Top 10 Products by Revenue</h2>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left">#</th>
                <th class="px-4 py-2 text-left">Product</th>
                <th class="px-4 py-2 text-left">Units Sold</th>
                <th class="px-4 py-2 text-left">Revenue</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($topProducts as $i => $product)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2">{{ $i + 1 }}</td>
                <td class="px-4 py-2 font-medium">{{ $product->name }}</td>
                <td class="px-4 py-2">{{ $product->total_sold }}</td>
                <td class="px-4 py-2">${{ number_format($product->total_revenue, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Daily Sales --}}
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">Daily Sales (Last 7 days)</h2>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Orders</th>
                <th class="px-4 py-2 text-left">Revenue</th>
                <th class="px-4 py-2 text-left">Avg Order</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($daily as $row)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($row->period)->format('M d, Y') }}</td>
                <td class="px-4 py-2">{{ $row->total_orders }}</td>
                <td class="px-4 py-2">${{ number_format($row->total_revenue, 2) }}</td>
                <td class="px-4 py-2">
                    ${{ $row->total_orders > 0 ? number_format($row->total_revenue / $row->total_orders, 2) : '0.00' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-2 text-gray-500">No data yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Monthly Sales --}}
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold mb-4">Monthly Sales</h2>
    <table class="w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-left">Month</th>
                <th class="px-4 py-2 text-left">Orders</th>
                <th class="px-4 py-2 text-left">Revenue</th>
                <th class="px-4 py-2 text-left">Avg Order</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($monthly as $row)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($row->period)->format('M Y') }}</td>
                <td class="px-4 py-2">{{ $row->total_orders }}</td>
                <td class="px-4 py-2">${{ number_format($row->total_revenue, 2) }}</td>
                <td class="px-4 py-2">
                    ${{ $row->total_orders > 0 ? number_format($row->total_revenue / $row->total_orders, 2) : '0.00' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-2 text-gray-500">No data yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
