<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;

/**
 * Database-backed settings that override .env at runtime, so self-hosted
 * installs can configure mail (and future options) from the admin UI.
 */
class MailSettings
{
    public const MAILER_LOG = 'log';

    public const MAILER_SMTP = 'smtp';

    /** Transport schemes this Laravel version accepts for the smtp mailer. */
    public const SCHEME_STARTTLS = 'smtp';

    public const SCHEME_IMPLICIT_TLS = 'smtps';

    public const SCHEME_NONE = 'none';

    /** Values the UI stored before the scheme fix; upgraded transparently on read. */
    private const LEGACY_SCHEMES = [
        'tls' => self::SCHEME_STARTTLS,
        'ssl' => self::SCHEME_IMPLICIT_TLS,
    ];

    public static function normalizeScheme(?string $scheme): string
    {
        $scheme = self::LEGACY_SCHEMES[$scheme] ?? $scheme;

        return in_array($scheme, [self::SCHEME_STARTTLS, self::SCHEME_IMPLICIT_TLS, self::SCHEME_NONE], true)
            ? $scheme
            : self::SCHEME_STARTTLS;
    }

    /** Keys whose values are stored encrypted (they are credentials). */
    private const SECRET_KEYS = ['mail_password'];

    private ?array $resolved = null;

    /**
     * All settings, decrypted, merged over the effective .env-derived config
     * so the UI always shows what mail would actually use.
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        try {
            $stored = Setting::query()->pluck('value', 'key');
        } catch (QueryException) {
            // Table not migrated yet (fresh install mid-migration).
            $stored = collect();
        }

        $settings = [
            'mail_mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_password' => config('mail.mailers.smtp.password'),
            'mail_encryption' => self::normalizeScheme(config('mail.mailers.smtp.scheme')),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ];

        foreach ($stored as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (in_array($key, self::SECRET_KEYS, true)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable) {
                    $value = null;
                }
            }

            $settings[$key] = $value;
        }

        $settings['mail_encryption'] = self::normalizeScheme($settings['mail_encryption']);

        return $this->resolved = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Persist settings. A null password keeps the previously stored one so
     * saving other fields never wipes the credential.
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($key === 'mail_password' && ($value === null || $value === '')) {
                continue;
            }

            if (in_array($key, self::SECRET_KEYS, true) && is_string($value)) {
                $value = Crypt::encryptString($value);
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->resolved = null;
    }

    /**
     * Overlay the stored settings onto the mail config. Runs on every
     * request so Mailables pick the UI-configured transport up.
     */
    public function apply(): void
    {
        $settings = $this->all();

        $mailer = $settings['mail_mailer'] ?? self::MAILER_LOG;
        config()->set('mail.default', $mailer);

        if ($mailer === self::MAILER_SMTP) {
            config()->set('mail.mailers.smtp.host', $settings['mail_host'] ?? null);
            config()->set('mail.mailers.smtp.port', (int) ($settings['mail_port'] ?? 587));
            config()->set('mail.mailers.smtp.username', $settings['mail_username'] ?? null);
            config()->set('mail.mailers.smtp.password', $settings['mail_password'] ?? null);

            $scheme = self::normalizeScheme($settings['mail_encryption'] ?? null);
            config()->set('mail.mailers.smtp.scheme', $scheme === self::SCHEME_NONE ? null : $scheme);
        }

        if (! empty($settings['mail_from_address'])) {
            config()->set('mail.from.address', $settings['mail_from_address']);
        }

        if (! empty($settings['mail_from_name'])) {
            config()->set('mail.from.name', $settings['mail_from_name']);
        }
    }

    public function mailer(): string
    {
        return (string) ($this->get('mail_mailer') ?? self::MAILER_LOG);
    }

    public function isSmtpConfigured(): bool
    {
        return $this->mailer() === self::MAILER_SMTP
            && filled($this->get('mail_host'))
            && filled($this->get('mail_from_address'));
    }
}
