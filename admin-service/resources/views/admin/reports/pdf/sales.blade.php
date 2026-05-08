<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1a1a2e; font-size: 20px; margin-bottom: 5px; }
        .subtitle { color: #666; font-size: 11px; margin-bottom: 20px; }
        .stats { display: flex; gap: 20px; margin-bottom: 25px; }
        .stat-box { background: #f8f9fa; border: 1px solid #dee2e6;
                    padding: 12px 20px; border-radius: 4px; min-width: 140px; }
        .stat-label { font-size: 10px; color: #666; text-transform: uppercase; }
        .stat-value { font-size: 18px; font-weight: bold; color: #1a1a2e; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        thead tr { background: #1a1a2e; color: white; }
        th { padding: 8px 12px; text-align: left; font-size: 11px; }
        td { padding: 7px 12px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #f8f9fa; }
        .footer { margin-top: 30px; font-size: 10px; color: #999;
                  border-top: 1px solid #eee; padding-top: 10px; }
        .right { text-align: right; }
        .section-title { font-size: 14px; font-weight: bold;
                         margin: 20px 0 10px; color: #1a1a2e; }
    </style>
</head>
<body>
    <h1>Sales Report — {{ $period }}</h1>
    <div class="subtitle">Generated on {{ now()->format('F d, Y H:i') }}</div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value">{{ $summary['total_orders'] }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">${{ number_format($summary['total_revenue'], 2) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Avg Order Value</div>
            <div class="stat-value">
                ${{ $summary['total_orders'] > 0
                    ? number_format($summary['total_revenue'] / $summary['total_orders'], 2)
                    : '0.00' }}
            </div>
        </div>
    </div>

    <div class="section-title">Sales Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Orders</th>
                <th class="right">Revenue</th>
                <th class="right">Avg Order</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row->period)->format($dateFormat) }}</td>
                <td>{{ $row->total_orders }}</td>
                <td class="right">${{ number_format($row->total_revenue, 2) }}</td>
                <td class="right">
                    ${{ $row->total_orders > 0
                        ? number_format($row->total_revenue / $row->total_orders, 2)
                        : '0.00' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;color:#999">No data available</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(count($topProducts) > 0)
    <div class="section-title">Top Products by Revenue</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Units Sold</th>
                <th class="right">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $i => $product)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->total_sold }}</td>
                <td class="right">${{ number_format($product->total_revenue, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Ecommerce Platform — Confidential — {{ now()->format('Y') }}
    </div>
</body>
</html>
