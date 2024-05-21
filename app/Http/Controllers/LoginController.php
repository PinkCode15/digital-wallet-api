<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Log in a user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->validated())) {
            return response()->json([
                'message' => 'Incorrect login credentials',
                'data' => []
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;
        $tokenExpiry = Carbon::now()->addMinutes(config('sanctum.expiration'));

        return response()->json([
            'message' => 'Login successful', 
            'data' => [
                'email' => $user->email,
                'token' => $token,
                'token_expiry' => $tokenExpiry
            ]
        ], 200);
    }

    /**
     * Log out a user.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $user = Auth::user();

        if(!$user){
            return response()->json([
                'message' => 'Unauthorized',
                'data' => []
            ], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logout SUccessful',
            'data' => []
        ], 401);
    }
}
