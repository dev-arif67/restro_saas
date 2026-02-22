<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Services\AI\MenuDescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuDescriptionController extends Controller
{
    protected MenuDescriptionService $descriptionService;

    public function __construct(MenuDescriptionService $descriptionService)
    {
        $this->descriptionService = $descriptionService;
    }

    /**
     * Generate a description for a menu item.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'style' => 'nullable|string|in:appetizing,formal,casual,playful',
            'length' => 'nullable|string|in:short,medium,long',
            'language' => 'nullable|string|in:en,bn',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $result = $this->descriptionService
            ->forTenant($tenantId)
            ->generateDescription(
                $request->input('name'),
                $request->input('category'),
                $request->input('price'),
                [
                    'style' => $request->input('style', 'appetizing'),
                    'length' => $request->input('length', 'medium'),
                    'language' => $request->input('language', 'en'),
                ]
            );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Generate multiple description alternatives.
     */
    public function alternatives(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'price' => 'nullable|numeric|min:0',
            'count' => 'nullable|integer|min:2|max:5',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $result = $this->descriptionService
            ->forTenant($tenantId)
            ->generateAlternatives(
                $request->input('name'),
                $request->input('category'),
                $request->input('price'),
                $request->input('count', 3)
            );

        return response()->json($result);
    }

    /**
     * Improve an existing description.
     */
    public function improve(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'category' => 'nullable|string|max:100',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $result = $this->descriptionService
            ->forTenant($tenantId)
            ->improveDescription(
                $request->input('name'),
                $request->input('description'),
                $request->input('category')
            );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Generate descriptions for multiple items.
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1|max:20',
            'items.*.name' => 'required|string|max:255',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.id' => 'nullable|integer',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $result = $this->descriptionService
            ->forTenant($tenantId)
            ->generateBatch($request->input('items'));

        return response()->json($result);
    }

    /**
     * Generate and apply description to a menu item.
     */
    public function generateAndApply(Request $request, int $id): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $menuItem = MenuItem::where('tenant_id', $tenantId)
            ->findOrFail($id);

        $result = $this->descriptionService
            ->forTenant($tenantId)
            ->generateDescription(
                $menuItem->name,
                $menuItem->category?->name,
                $menuItem->price,
                [
                    'style' => $request->input('style', 'appetizing'),
                    'length' => $request->input('length', 'medium'),
                    'language' => $request->input('language', 'en'),
                ]
            );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        // Apply if requested
        if ($request->boolean('apply', false)) {
            $menuItem->update(['description' => $result['description']]);
            $result['applied'] = true;
        }

        return response()->json($result);
    }
}
