<?php

namespace App\Services;

use App\Models\Setting;

class DomainValidationService
{
    public function isDomainAllowed(string $email): bool
    {
        $setting = Setting::where('key', 'allowed_domains')->first();

        if (!$setting || empty(trim($setting->value))) {
            return true; // No restrictions — all domains allowed
        }

        $allowedDomains = array_map('trim', explode(',', $setting->value));
        $emailDomain = strtolower(substr(strrchr($email, '@'), 1));

        return in_array($emailDomain, array_map('strtolower', $allowedDomains), true);
    }
}
