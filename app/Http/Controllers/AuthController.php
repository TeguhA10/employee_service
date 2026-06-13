<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        if (! $user) {
            return response()->json([
                'message' => 'user not found',
            ], 404);
        }

        return response()
            ->json([
                'message' => 'user',
                'data' => $user
            ]);
    }
}
