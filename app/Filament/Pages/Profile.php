<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class Profile extends Page
{

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'My account';

    protected string $view = 'filament.pages.profile';

    // Profile section
    public string $name = '';

    public string $email = '';

    // Password section
    public ?string $current_password = null;

    public ?string $new_password = null;

    public ?string $new_password_confirmation = null;

    // Email preferences
    public bool $prefUptime = true;

    public bool $prefSecurity = true;

    public bool $prefUpdates = true;

    // 2FA setup
    public ?string $twoFactorCode = null;

    public ?string $pendingSecret = null;

    public ?array $recoveryCodes = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->prefUptime = $user->wantsEmail('uptime');
        $this->prefSecurity = $user->wantsEmail('security');
        $this->prefUpdates = $user->wantsEmail('updates');
    }

    public function getTitle(): string
    {
        return 'My account';
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.auth()->id()],
        ], [], ['name' => 'name', 'email' => 'email address']);

        auth()->user()->forceFill(['name' => $this->name, 'email' => $this->email])->save();

        Notification::make()->title('Profile updated')->success()->send();
    }

    public function savePassword(): void
    {
        $user = auth()->user();

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'current_password.current_password' => 'Your current password is not correct.',
            'new_password.min' => 'Use at least 10 characters for the new password.',
        ], [
            'new_password' => 'new password',
            'new_password_confirmation' => 'confirmation',
        ]);

        $user->forceFill(['password' => $this->new_password])->save();
        $user->notify(new \App\Notifications\PasswordChangedNotification());

        // The password change invalidates other sessions.
        auth()->logoutOtherDevices($this->new_password);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        Notification::make()
            ->title('Password changed')
            ->body('Other browser sessions were signed out, and a confirmation email was sent.')
            ->success()
            ->send();
    }

    public function saveEmailPreferences(): void
    {
        auth()->user()->forceFill([
            'email_preferences' => [
                'uptime' => $this->prefUptime,
                'security' => $this->prefSecurity,
                'updates' => $this->prefUpdates,
            ],
        ])->save();

        Notification::make()->title('Email preferences saved')->success()->send();
    }

    // ---------------- 2FA ----------------

    public function startTwoFactor(): void
    {
        $this->pendingSecret = app(Google2FA::class)->generateSecretKey(32);
        $this->twoFactorCode = null;
    }

    public function qrCodeSvg(): ?string
    {
        if ($this->pendingSecret === null) {
            return null;
        }

        $uri = app(Google2FA::class)->getQRCodeUrl(
            config('app.name', 'Plugsent'),
            auth()->user()->email,
            $this->pendingSecret,
        );

        try {
            // chillerlan v5 returns a base64 data URI for SVG output,
            // rendered by the view in an <img> tag.
            return (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
            ])))->render($uri);
        } catch (Throwable) {
            return null;
        }
    }

    public function confirmTwoFactor(): void
    {
        $this->validate([
            'twoFactorCode' => ['required'],
        ], [], ['twoFactorCode' => 'authentication code']);

        $valid = app(Google2FA::class)->verifyKey($this->pendingSecret, $this->twoFactorCode);

        if (! $valid) {
            $this->addError('twoFactorCode', 'That code is not correct — check your authenticator app and try again.');

            return;
        }

        $user = auth()->user();
        $codes = app(\App\Filament\Auth\MultiFactor\TotpAuthentication::class)->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $this->pendingSecret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->recoveryCodes = $codes;
        $this->pendingSecret = null;
        $this->twoFactorCode = null;

        Notification::make()
            ->title('Two-factor authentication enabled')
            ->body('Store your recovery codes somewhere safe — they are shown only once.')
            ->success()
            ->send();
    }

    public function disableTwoFactor(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Your current password is not correct.',
        ]);

        auth()->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->reset(['current_password', 'recoveryCodes']);

        Notification::make()->title('Two-factor authentication disabled')->success()->send();
    }

    public function regenerateRecoveryCodes(): void
    {
        $codes = $this->generateRecoveryCodes();

        auth()->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->recoveryCodes = $codes;

        Notification::make()->title('New recovery codes generated')->body('Old codes no longer work.')->success()->send();
    }

}
