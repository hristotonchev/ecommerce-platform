@extends('layouts.admin')
@section('title', 'User Details')
@section('content')
<div class="max-w-3xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">{{ $user->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">← Back</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div><span class="text-gray-500">Email:</span> {{ $user->email }}</div>
            <div><span class="text-gray-500">Role:</span> {{ ucfirst($user->role) }}</div>
            <div><span class="text-gray-500">Joined:</span>
                {{ \Carbon\Carbon::parse($user->created_at)->format('M d, Y') }}
            </div>
        </div>
    </div>

    <h2 class="text-lg font-semibold mb-4">Orders ({{ count($orders) }})</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Total</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                <tr>
                    <td class="px-4 py-3">#{{ $order->id }}</td>
                    <td class="px-4 py-3">{{ ucfirst($order->status) }}</td>
                    <td class="px-4 py-3">${{ number_format($order->total_amount, 2) }}</td>
                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-3 text-gray-500">No orders yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
