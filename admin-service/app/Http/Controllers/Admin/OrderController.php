<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrderStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('orders as o')
            ->join('users as u', 'o.user_id', '=', 'u.id')
            ->select('o.*', 'u.name as user_name', 'u.email as user_email')
            ->orderBy('o.created_at', 'desc');

        if ($request->status) {
            $query->where('o.status', $request->status);
        }
        if ($request->search) {
            $query->where('u.email', 'ilike', '%' . $request->search . '%');
        }

        $orders = $query->paginate(20);
        return view('admin.orders.index', compact('orders'));
    }

    public function show($id)
    {
        $order = DB::table('orders as o')
            ->join('users as u', 'o.user_id', '=', 'u.id')
            ->select('o.*', 'u.name as user_name', 'u.email as user_email')
            ->where('o.id', $id)
            ->first();

        abort_if(!$order, 404);

        $items = DB::table('order_items as oi')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->select('oi.*', 'p.name as product_name')
            ->where('oi.order_id', $id)
            ->get();

        return view('admin.orders.show', compact('order', 'items'));
    }

    public function update(StoreOrderStatusRequest $request, $id)
    {
        DB::table('orders')->where('id', $id)->update([
            'status'     => $request->status,
            'updated_at' => now(),
        ]);

        $this->notifyNestjs($id, $request->status);

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Order status updated to ' . ucfirst($request->status) . '!');
    }

    private function notifyNestjs(int $orderId, string $status): void
    {
        // TODO: Same as ProductController — extract into a NestjsWebhookService.
        //       Also consider wrapping the HTTP call in a queued job so that a
        //       slow or unavailable Nest.js instance doesn't block the admin UI.
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 3]);
            $client->post(
                config('services.nestjs.url') . '/api/internal/orders/' . $orderId . '/status',
                [
                    'headers' => ['X-API-Key' => config('services.nestjs.api_key')],
                    'json'    => ['status' => $status],
                ]
            );
        } catch (\Exception $e) {
            \Log::warning('Nest.js notification failed: ' . $e->getMessage());
        }
    }
}
