<?php

namespace App\Filament\Pages;

use App\Support\MailSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Settings';

    protected string $view = 'filament.pages.settings';

    public string $mailer = MailSettings::MAILER_LOG;

    public ?string $host = null;

    public ?int $port = 587;

    public ?string $username = null;

    public ?string $password = null;

    public string $encryption = MailSettings::SCHEME_STARTTLS;

    public ?string $fromAddress = null;

    public ?string $fromName = null;

    public ?string $testEmail = null;

    public function mount(): void
    {
        $settings = app(MailSettings::class);
        $all = $settings->all();

        $this->mailer = in_array($all['mail_mailer'] ?? null, [MailSettings::MAILER_LOG, MailSettings::MAILER_SMTP], true)
            ? $all['mail_mailer']
            : MailSettings::MAILER_LOG;
        $this->host = $all['mail_host'] ?: null;
        $this->port = (int) ($all['mail_port'] ?: 587);
        $this->username = $all['mail_username'] ?: null;
        $this->encryption = MailSettings::normalizeScheme($all['mail_encryption'] ?? null);
        $this->fromAddress = $all['mail_from_address'] ?: null;
        $this->fromName = $all['mail_from_name'] ?: null;

        // The secret never round-trips to the browser; blank means "keep".
        $this->password = null;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->workspaces()->wherePivot('role', 'owner')->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Settings';
    }

    public function mailSmtpConfigured(): bool
    {
        return app(MailSettings::class)->isSmtpConfigured();
    }

    public function save(): void
    {
        $this->validate([
            'mailer' => ['required', 'in:log,smtp'],
            'host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:smtp,smtps,none'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:255'],
        ], [
            'host.required_if' => 'The SMTP host is required when the mailer is SMTP.',
            'fromAddress.required' => 'The "from" address is required — replies and bounces point there.',
        ]);

        $port = $this->port ?: ($this->mailer === MailSettings::MAILER_SMTP ? 587 : null);

        app(MailSettings::class)->save(array_filter([
            'mail_mailer' => $this->mailer,
            'mail_host' => $this->host,
            'mail_port' => $port !== null ? (string) $port : null,
            'mail_username' => $this->username,
            'mail_password' => $this->password,
            'mail_encryption' => $this->encryption,
            'mail_from_address' => $this->fromAddress,
            'mail_from_name' => $this->fromName,
        ], static fn ($value) => $value !== null));

        app(MailSettings::class)->apply();

        $this->password = null;

        Notification::make()
            ->title('Settings saved')
            ->body($this->mailer === MailSettings::MAILER_SMTP
                ? 'Invitations now send over SMTP. Use the test email below to verify.'
                : 'Mail is set to the log driver — emails are written to the log, not delivered. Switch to SMTP to deliver invitations.')
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmail' => ['required', 'email', 'max:255'],
        ], [
            'testEmail.required' => 'Enter an address to send the test email to.',
        ]);

        $settings = app(MailSettings::class);
        $settings->apply();

        if (! $settings->isSmtpConfigured()) {
            Notification::make()
                ->title('SMTP is not fully configured')
                ->body('Set the mailer to SMTP with a host and from address, save, then send a test email.')
                ->warning()
                ->send();

            return;
        }

        try {
            Mail::raw('This is a test email from Plugsent — if you can read this in your inbox, SMTP is working.', function ($message): void {
                $message->to($this->testEmail)->subject('Plugsent test email');
            });
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Test email failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Test email sent')
            ->body("Check the inbox of {$this->testEmail} (and its spam folder).")
            ->success()
            ->send();
    }
}
