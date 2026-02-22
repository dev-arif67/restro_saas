<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\SentimentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SentimentController extends Controller
{
    protected SentimentAnalysisService $sentimentService;

    public function __construct(SentimentAnalysisService $sentimentService)
    {
        $this->sentimentService = $sentimentService;
    }

    /**
     * Get tenant ID from authenticated user.
     */
    protected function getTenantId(): ?int
    {
        $user = Auth::guard('api')->user();
        return $user?->tenant_id;
    }

    /**
     * Get sentiment overview.
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $period = $request->get('period', '30d');

        $result = $this->sentimentService
            ->forTenant($tenantId)
            ->getSentimentOverview($period);

        return response()->json($result);
    }

    /**
     * Get sentiment trends over time.
     */
    public function trends(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $days = (int) $request->get('days', 14);
        $days = max(7, min(90, $days));

        $result = $this->sentimentService
            ->forTenant($tenantId)
            ->getSentimentTrends($days);

        return response()->json($result);
    }

    /**
     * Get negative feedback requiring attention.
     */
    public function negativeFeedback(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $limit = (int) $request->get('limit', 10);
        $limit = max(1, min(50, $limit));

        $result = $this->sentimentService
            ->forTenant($tenantId)
            ->getNegativeFeedback($limit);

        return response()->json($result);
    }

    /**
     * Analyze a single text.
     */
    public function analyze(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $result = $this->sentimentService
            ->forTenant($tenantId)
            ->analyzeSentiment($request->input('text'));

        return response()->json($result);
    }
}
