<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function show($id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        $orders = DB::table('orders')->where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.users.show', compact('user', 'orders'));
    }

    public function destroy($id)
    {
        DB::table('users')->where('id', $id)->update(['deleted_at' => now()]);
        return redirect()->route('admin.users.index')
            ->with('success', 'User deactivated!');
    }
}
