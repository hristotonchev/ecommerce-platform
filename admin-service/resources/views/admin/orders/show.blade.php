@extends('layouts.admin')
@section('title', 'Order #{{ $order->id }}')
@section('content')
<div class="max-w-3xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Order #{{ $order->id }}</h1>
        <a href="{{ route('admin.orders.index') }}" class="text-blue-600 hover:underline">← Back</a>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold mb-3">Customer</h2>
            <p>{{ $order->user_name }}</p>
            <p class="text-gray-500">{{ $order->user_email }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-semibold mb-3">Update Status</h2>
            <form method="POST" action="{{ route('admin.orders.update', $order->id) }}">
                @csrf @method('PUT')
                <select name="status" class="border rounded px-3 py-2 w-full mb-3">
                    @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>
                            {{ ucfirst($s) }}
                        </option>
                    @endforeach
                </select>
                <button class="bg-blue-600 text-white px-4 py-2 rounded w-full">Update</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Qty</th>
                    <th class="px-4 py-3 text-left">Unit Price</th>
                    <th class="px-4 py-3 text-left">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($items as $item)
                <tr>
                    <td class="px-4 py-3">{{ $item->product_name }}</td>
                    <td class="px-4 py-3">{{ $item->quantity }}</td>
                    <td class="px-4 py-3">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="px-4 py-3">${{ number_format($item->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 font-bold">
                    <td colspan="3" class="px-4 py-3 text-right">Total:</td>
                    <td class="px-4 py-3">${{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
