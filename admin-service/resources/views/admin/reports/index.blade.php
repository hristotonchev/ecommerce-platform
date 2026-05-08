@extends('layouts.admin')
@section('title', 'Sales Reports')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Sales Reports</h1>
    <div class="flex space-x-2">
        <a href="{{ route('admin.reports.export', ['period' => 'daily', 'format' => 'csv']) }}"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Daily CSV
        </a>
        <a href="{{ route('admin.reports.export', ['period' => 'monthly', 'format' => 'csv']) }}"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Monthly CSV
        </a>
        <a href="{{ route('admin.reports.export', ['period' => 'daily', 'format' => 'pdf']) }}"
           class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
            Daily PDF
        </a>
        <a href="{{ route('admin.reports.export', ['period' => 'monthly', 'format' => 'pdf']) }}"
           class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
            Monthly PDF
        </a>
    </div>
</div>

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
            @forelse($topProducts as $i => $product)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2">{{ $i + 1 }}</td>
                <td class="px-4 py-2 font-medium">{{ $product->name }}</td>
                <td class="px-4 py-2">{{ $product->total_sold }}</td>
                <td class="px-4 py-2">${{ number_format($product->total_revenue, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-3 text-gray-500 text-center">No data yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

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
                    ${{ $row->total_orders > 0
                        ? number_format($row->total_revenue / $row->total_orders, 2)
                        : '0.00' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-3 text-gray-500 text-center">No data yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

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
                    ${{ $row->total_orders > 0
                        ? number_format($row->total_revenue / $row->total_orders, 2)
                        : '0.00' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-3 text-gray-500 text-center">No data yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
