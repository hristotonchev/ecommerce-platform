<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $daily   = $this->getSalesData('day', 7);
        $weekly  = $this->getSalesData('week', 8);
        $monthly = $this->getSalesData('month', 12);

        $topProducts = DB::table('order_items as oi')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->where('o.status', '!=', 'cancelled')
            ->select(
                'p.name',
                DB::raw('SUM(oi.quantity) as total_sold'),
                DB::raw('SUM(oi.subtotal) as total_revenue')
            )
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return view('admin.reports.index', compact('daily', 'weekly', 'monthly', 'topProducts'));
    }

    public function export(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $data   = $this->getSalesData($period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month'), 12);

        $filename = "sales-report-{$period}-" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data, $period) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Period', 'Total Orders', 'Total Revenue', 'Avg Order Value']);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->period,
                    $row->total_orders,
                    number_format($row->total_revenue, 2),
                    $row->total_orders > 0
                        ? number_format($row->total_revenue / $row->total_orders, 2)
                        : '0.00',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getSalesData(string $interval, int $limit)
    {
        return DB::table('orders')
            ->where('status', '!=', 'cancelled')
            ->select(
                DB::raw("DATE_TRUNC('{$interval}', created_at) as period"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_revenue')
            )
            ->groupBy('period')
            ->orderByDesc('period')
            ->limit($limit)
            ->get();
    }
}
