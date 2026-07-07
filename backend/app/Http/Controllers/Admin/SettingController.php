<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');
        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        // Validate allowed_domains format if present
        if (isset($data['settings']['allowed_domains']) && !empty($data['settings']['allowed_domains'])) {
            $domains = explode(',', $data['settings']['allowed_domains']);
            foreach ($domains as $domain) {
                $domain = trim($domain);
                if (empty($domain)) {
                    return response()->json(['message' => 'Domain list contains empty entries.', 'errors' => ['allowed_domains' => ['Domain list contains empty entries. Remove extra commas.']]], 422);
                }
                if (str_contains($domain, '*') || str_contains($domain, ' ')) {
                    return response()->json(['message' => "Invalid domain: '{$domain}'. No wildcards or spaces allowed.", 'errors' => ['allowed_domains' => ["Invalid domain: '{$domain}'."]]], 422);
                }
            }
        }

        foreach ($data['settings'] as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
