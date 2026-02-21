<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends BaseApiController
{
    /**
     * Get current tenant's branding.
     */
    public function show(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        return $this->success($tenant->only([
            'id', 'name', 'slug', 'logo', 'logo_dark', 'favicon',
            'primary_color', 'secondary_color', 'accent_color',
            'description', 'social_links', 'banner_image',
            'vat_registered', 'vat_number', 'default_vat_rate', 'vat_inclusive',
        ]));
    }

    /**
     * Update restaurant branding (restaurant admin).
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        $request->validate([
            'name'            => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:500',
            'primary_color'   => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'accent_color'    => 'nullable|string|max:20',
            'social_links'    => 'nullable|array',
            'social_links.facebook'  => 'nullable|url',
            'social_links.instagram' => 'nullable|url',
            'social_links.website'   => 'nullable|url',
            'logo'            => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'logo_dark'       => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'favicon'         => 'nullable|image|mimes:png,ico,svg|max:512',
            'banner_image'    => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'vat_registered'  => 'nullable|boolean',
            'vat_number'      => 'nullable|string|max:20',
            'default_vat_rate'=> 'nullable|numeric|min:0|max:100',
            'vat_inclusive'   => 'nullable|boolean',
        ]);

        $data = $request->only([
            'name', 'description', 'primary_color',
            'secondary_color', 'accent_color', 'social_links',
            'vat_registered', 'vat_number', 'default_vat_rate', 'vat_inclusive',
        ]);

        // Handle file uploads
        $fileFields = ['logo', 'logo_dark', 'favicon', 'banner_image'];
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                // Delete old file
                if ($tenant->$field) {
                    Storage::disk('public')->delete($tenant->$field);
                }
                $data[$field] = $request->file($field)->store("tenants/{$tenant->id}/branding", 'public');
            }
        }

        // Handle file removals
        foreach ($fileFields as $field) {
            if ($request->boolean("remove_{$field}")) {
                if ($tenant->$field) {
                    Storage::disk('public')->delete($tenant->$field);
                }
                $data[$field] = null;
            }
        }

        $tenant->update($data);

        return $this->success($tenant->fresh()->only([
            'id', 'name', 'slug', 'logo', 'logo_dark', 'favicon',
            'primary_color', 'secondary_color', 'accent_color',
            'description', 'social_links', 'banner_image',
            'vat_registered', 'vat_number', 'default_vat_rate', 'vat_inclusive',
        ]), 'Branding updated successfully');
    }
}
