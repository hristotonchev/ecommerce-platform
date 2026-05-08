<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index()
    {
        $daily   = $this->getSalesData('day', 7);
        $weekly  = $this->getSalesData('week', 8);
        $monthly = $this->getSalesData('month', 12);

        $topProducts = $this->getTopProducts();

        return view('admin.reports.index', compact('daily', 'weekly', 'monthly', 'topProducts'));
    }

    public function export(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $format = $request->get('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportPdf($period);
        }

        return $this->exportCsv($period);
    }

    private function exportCsv(string $period)
    {
        $interval = $period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month');
        $data     = $this->getSalesData($interval, 12);
        $filename = "sales-report-{$period}-" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($data) {
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

    private function exportPdf(string $period)
    {
        $interval   = $period === 'daily' ? 'day' : ($period === 'weekly' ? 'week' : 'month');
        $dateFormat = $period === 'daily' ? 'M d, Y' : ($period === 'weekly' ? 'W-Y' : 'M Y');
        $data       = $this->getSalesData($interval, 12);
        $topProducts = $this->getTopProducts();

        $summary = [
            'total_orders'  => $data->sum('total_orders'),
            'total_revenue' => $data->sum('total_revenue'),
        ];

        $pdf = Pdf::loadView('admin.reports.pdf.sales', [
            'data'        => $data,
            'topProducts' => $topProducts,
            'summary'     => $summary,
            'period'      => ucfirst($period) . ' Report',
            'dateFormat'  => $dateFormat,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("sales-report-{$period}-" . now()->format('Y-m-d') . ".pdf");
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

    private function getTopProducts()
    {
        return DB::table('order_items as oi')
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
    }
}
