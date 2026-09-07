<?php

namespace App\Filament\Auth\MultiFactor;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;
use Throwable;

/**
 * TOTP two-factor authentication (authenticator app), backed by the
 * user's encrypted `two_factor_secret`. During the login challenge the
 * user may enter either a 6-digit TOTP or one of their recovery codes
 * (which is then consumed).
 */
class TotpAuthentication implements MultiFactorAuthenticationProvider
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'totp';
    }

    public function getLoginFormLabel(): string
    {
        return 'Authentication code';
    }

    public function isEnabled(Authenticatable $user): bool
    {
        return $user instanceof User && $user->hasTwoFactorEnabled();
    }

    /**
     * Setup lives on the custom Profile page (QR + secret + recovery
     * codes), so nothing is rendered inside Filament's generic profile.
     *
     * @return array<Component>
     */
    public function getManagementSchemaComponents(): array
    {
        return [];
    }

    /**
     * @param  Authenticatable&User  $user
     * @return array<Component|Action>
     */
    public function getChallengeFormComponents(Authenticatable $user): array
    {
        return [
            OneTimeCodeInput::make('code')
                ->label('Authentication code')
                ->validationAttribute('code')
                ->required()
                ->rule(function () use ($user): Closure {
                    return function (string $attribute, #[SensitiveParameter] $value, Closure $fail) use ($user): void {
                        if (is_string($value) && $this->verifyChallenge($user, $value)) {
                            return;
                        }

                        $fail('That code is not correct — check your authenticator app or use a recovery code.');
                    };
                })
                ->belowContent('Lost your device? Enter one of your recovery codes instead of the 6-digit code.'),
        ];
    }

    /**
     * Accept either a TOTP (±1 tick of clock drift) or an unused recovery
     * code, which is consumed on success.
     */
    public function verifyChallenge(User $user, #[SensitiveParameter] string $value): bool
    {
        $value = strtoupper(str_replace([' ', '-'], '', trim($value)));

        if (preg_match('/^\d{6}$/', $value)) {
            return $this->verifyTotp($user, $value);
        }

        return $this->consumeRecoveryCode($user, $value);
    }

    public function verifyTotp(User $user, #[SensitiveParameter] string $code): bool
    {
        if (empty($user->two_factor_secret)) {
            return false;
        }

        try {
            return (bool) app(Google2FA::class)->verifyKey($user->two_factor_secret, $code, 1);
        } catch (Throwable) {
            return false;
        }
    }

    public function consumeRecoveryCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hashed) {
            if (Hash::check($code, $hashed)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(substr(bin2hex(random_bytes(4)), 0, 5).'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 5))))
            ->all();
    }
}
