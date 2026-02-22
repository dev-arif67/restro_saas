<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AI\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected RecommendationService $recommendations;

    public function __construct(RecommendationService $recommendations)
    {
        $this->recommendations = $recommendations;
    }

    /**
     * Get smart recommendations.
     */
    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::where(is_numeric($tenant) ? 'id' : 'slug', $tenant)->first();

        if (!$tenantModel || !$tenantModel->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Restaurant not found',
            ], 404);
        }

        $cartItems = $request->input('cart', []);
        $limit = min($request->input('limit', 6), 12);

        $result = $this->recommendations
            ->forTenant($tenantModel->id)
            ->getRecommendations($cartItems, $limit);

        return response()->json($result);
    }

    /**
     * Get frequently bought together items.
     */
    public function frequentlyBoughtTogether(Request $request, string $tenant, int $itemId): JsonResponse
    {
        $tenantModel = Tenant::where(is_numeric($tenant) ? 'id' : 'slug', $tenant)->first();

        if (!$tenantModel || !$tenantModel->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Restaurant not found',
            ], 404);
        }

        $limit = min($request->input('limit', 3), 6);

        $items = $this->recommendations
            ->forTenant($tenantModel->id)
            ->getFrequentlyBoughtTogether($itemId, $limit);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }
}
