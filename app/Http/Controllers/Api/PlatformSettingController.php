<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PlatformSettingController extends BaseApiController
{
    /**
     * Get all branding settings (super admin).
     */
    public function index(): JsonResponse
    {
        $settings = PlatformSetting::where('group', 'branding')
            ->get()
            ->mapWithKeys(fn($s) => [$s->key => $s->value]);

        return $this->success($settings);
    }

    /**
     * Update branding settings (super admin).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'platform_name'    => 'nullable|string|max:100',
            'primary_color'    => 'nullable|string|max:20',
            'secondary_color'  => 'nullable|string|max:20',
            'footer_text'      => 'nullable|string|max:255',
            'powered_by_text'  => 'nullable|string|max:100',
            'powered_by_url'   => 'nullable|url|max:255',
            'platform_logo'    => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'platform_logo_dark' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'platform_favicon' => 'nullable|image|mimes:png,ico,svg|max:512',
        ]);

        $textFields = ['platform_name', 'primary_color', 'secondary_color', 'footer_text', 'powered_by_text', 'powered_by_url'];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                PlatformSetting::setValue($field, $request->input($field), 'text', 'branding');
            }
        }

        // Handle file uploads
        $fileFields = ['platform_logo', 'platform_logo_dark', 'platform_favicon'];
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                // Delete old file if exists
                $oldPath = PlatformSetting::getValue($field);
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }

                $path = $request->file($field)->store('platform/branding', 'public');
                PlatformSetting::setValue($field, $path, 'image', 'branding');
            }
        }

        // Delete specific file if requested
        foreach ($fileFields as $field) {
            if ($request->boolean("remove_{$field}")) {
                $oldPath = PlatformSetting::getValue($field);
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
                PlatformSetting::setValue($field, null, 'image', 'branding');
            }
        }

        Cache::forget('platform_settings.branding');

        return $this->success(PlatformSetting::getBranding(), 'Platform branding updated');
    }

    /**
     * Public endpoint: get platform branding for any panel.
     */
    public function branding(): JsonResponse
    {
        return $this->success(PlatformSetting::getBranding());
    }
}
