<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseApiController
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        $user = auth()->user();

        if (!$user->isActive()) {
            auth()->logout();
            return $this->error('Account is inactive. Contact administrator.', 403);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Registration is disabled.
     * Only super admin can create tenants and users via admin routes.
     */
    // public function register(Request $request): JsonResponse
    // {
    //     return $this->error('Registration is disabled. Contact the super admin to create an account.', 403);
    // }
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'restaurant_admin',
            'status' => 'pending',
        ]);

        $token = auth()->login($user);

        return $this->respondWithToken($token, 201);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();
        $user->load('tenant');

        return $this->success([
            'user' => $user,
            'subscription' => $user->tenant?->activeSubscription,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->logout();
        return $this->success(null, 'Logged out successfully');
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    protected function respondWithToken(string $token, int $code = 200): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
        ], $code);
    }
}
