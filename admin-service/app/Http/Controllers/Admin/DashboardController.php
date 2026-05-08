<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // TODO: Cache these aggregate queries in Redis (Cache::remember) with a
        //       TTL of ~60 s.  At high order volumes each dashboard page load runs
        //       5 full-table COUNT/SUM queries which will slow down noticeably.
        $stats = [
            'total_orders'    => DB::table('orders')->count(),
            'total_revenue'   => DB::table('orders')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'total_products'  => DB::table('products')
                ->whereNull('deleted_at')
                ->count(),
            'total_users'     => DB::table('users')
                ->where('role', 'customer')
                ->count(),
            'pending_orders'  => DB::table('orders')
                ->where('status', 'pending')
                ->count(),
            'recent_orders'   => DB::table('orders')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
