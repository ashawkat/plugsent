<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Small typed accessor over the settings table for non-mail values.
 * Secrets are stored encrypted; reading tolerates legacy plaintext.
 */
class AppSettings
{
    public const WORDFENCE_API_KEY = 'wf_api_key';

    public const VULN_LAST_SYNC = 'vuln_last_sync';

    public function get(string $key, ?string $default = null): ?string
    {
        try {
            $value = Setting::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $default;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return $this->maybeDecrypt($value) ?? $default;
    }

    public function put(string $key, ?string $value, bool $encrypt = false): void
    {
        if ($value === null) {
            Setting::query()->where('key', $key)->delete();

            return;
        }

        $stored = $encrypt ? Crypt::encryptString($value) : $value;

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    private function maybeDecrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value; // legacy plaintext
        }
    }
}
