<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()
            ->withCount(['users', 'orders'])
            ->with('activeSubscription');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->latest());
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . Str::random(5),
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'authorized_wifi_ip' => $request->authorized_wifi_ip,
                'payment_mode' => $request->payment_mode ?? 'seller',
                'commission_rate' => $request->commission_rate ?? 0,
                'tax_rate' => $request->tax_rate ?? 0,
                'max_users' => $request->max_users ?? 5,
            ]);

            // Create admin user for tenant
            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => $request->admin_password,
                'role' => User::ROLE_RESTAURANT_ADMIN,
            ]);

            // Create subscription
            $planDays = match ($request->plan_type) {
                'monthly' => 30,
                'yearly' => 365,
                'custom' => $request->custom_days ?? 30,
            };

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_type' => $request->plan_type,
                'amount' => $request->subscription_amount,
                'payment_method' => $request->payment_method,
                'payment_ref' => $request->payment_ref,
                'starts_at' => now(),
                'expires_at' => now()->addDays($planDays),
                'status' => 'active',
            ]);

            return $this->created([
                'tenant' => $tenant->fresh()->load('activeSubscription'),
                'admin' => $admin,
            ], 'Restaurant onboarded successfully');
        });
    }

    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::with(['activeSubscription', 'users'])
            ->withCount(['tables', 'menuItems', 'orders', 'vouchers'])
            ->find($id);

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        return $this->success($tenant);
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('tenants/logos', 'public');
            $request->merge(['logo' => $path]);
        }

        $tenant->update($request->validated());

        return $this->success($tenant->fresh(), 'Tenant updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        $tenant->update(['is_active' => false]);

        return $this->success(null, 'Tenant deactivated successfully');
    }

    public function dashboard(): JsonResponse
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return $this->success([
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('is_active', true)->count(),
                'total_users' => User::count(),
                'total_revenue' => Subscription::where('status', 'active')->sum('amount'),
            ]);
        }

        return $this->forbidden();
    }
}
