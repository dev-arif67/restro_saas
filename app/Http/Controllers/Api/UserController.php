<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    /**
     * List users.
     * - Super admin: can list all users, optionally filter by tenant_id
     * - Restaurant admin: can only see own tenant's users (read-only)
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = auth()->user();
        $query = User::query();

        if ($authUser->isSuperAdmin()) {
            // Super admin can filter by tenant_id
            if ($tenantId = $request->get('tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }
        } else {
            // Non-super-admin can only see their own tenant's users
            $query->where('tenant_id', $authUser->tenant_id);
        }

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

    /**
     * Create user (super admin only — enforced by route middleware).
     * Super admin must provide tenant_id.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->created($user->load('tenant:id,name'), 'User created');
    }

    /**
     * Show a user.
     * - Super admin: any user
     * - Restaurant admin: only own tenant's users
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->resolveUser($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success($user->load('tenant:id,name'));
    }

    /**
     * Update user (super admin only — enforced by route middleware).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

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
            'tenant_id' => 'sometimes|exists:tenants,id',
        ]);

        $user->update($validated);

        return $this->success($user->fresh()->load('tenant:id,name'), 'User updated');
    }

    /**
     * Deactivate user (super admin only — enforced by route middleware).
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        if ($user->id === auth()->id()) {
            return $this->error('Cannot delete yourself', 422);
        }

        if ($user->isSuperAdmin()) {
            return $this->error('Cannot deactivate a super admin', 422);
        }

        $user->update(['status' => 'inactive']);

        return $this->success(null, 'User deactivated');
    }

    /**
     * Resolve user based on role:
     * - Super admin sees any user
     * - Restaurant admin sees only own tenant's users
     */
    private function resolveUser(int $id): ?User
    {
        $authUser = auth()->user();

        if ($authUser->isSuperAdmin()) {
            return User::find($id);
        }

        return User::where('tenant_id', $authUser->tenant_id)->find($id);
    }
}
