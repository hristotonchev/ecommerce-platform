<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $jwtUser = $request->jwt_user;
        $user    = DB::table('users')->where('id', $jwtUser['sub'])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return new UserResource($user);
    }

    public function orders(Request $request)
    {
        $userId = $request->jwt_user['sub'];

        $orders = DB::table('orders')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return OrderResource::collection($orders);
    }
}
