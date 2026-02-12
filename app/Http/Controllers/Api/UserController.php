<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('tenant_id', auth()->user()->tenant_id);

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->latest());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$request->validated(),
        ]);

        return $this->created($user, 'User created');
    }

    public function show(int $id): JsonResponse
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success($user);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$id}",
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:restaurant_admin,staff,kitchen',
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $user->update($validated);

        return $this->success($user->fresh(), 'User updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::where('tenant_id', auth()->user()->tenant_id)
            ->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        if ($user->id === auth()->id()) {
            return $this->error('Cannot delete yourself', 422);
        }

        $user->update(['status' => 'inactive']);

        return $this->success(null, 'User deactivated');
    }
}
