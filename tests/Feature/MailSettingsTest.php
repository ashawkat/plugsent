<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Pages\Settings;
use App\Filament\Pages\Team;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_only_accessible_to_workspace_owners(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $member = User::factory()->create();
        $workspace->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)
            ->get("/app/{$workspace->slug}/settings")
            ->assertForbidden();

        $this->actingAs($owner)
            ->get("/app/{$workspace->slug}/settings")
            ->assertOk()
            ->assertSee('Log driver (no real delivery)');
    }

    public function test_owner_can_save_smtp_settings_which_override_env(): void
    {
        $owner = User::factory()->create();
        app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->actingAs($owner);

        Livewire::test(Settings::class)
            ->set('mailer', 'smtp')
            ->set('host', 'smtp.betatech.co')
            ->set('port', 2525)
            ->set('username', 'adnan')
            ->set('password', 'super-secret')
            ->set('encryption', 'smtps')
            ->set('fromAddress', 'hello@betatech.co')
            ->set('fromName', 'BetaTech')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(MailSettings::class);

        // The secret is returned decrypted but stored encrypted.
        $this->assertSame('super-secret', $settings->get('mail_password'));
        $this->assertNotSame('super-secret', Setting::query()->where('key', 'mail_password')->value('value'));

        (new MailSettings)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.betatech.co', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('adnan', config('mail.mailers.smtp.username'));
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('hello@betatech.co', config('mail.from.address'));
        $this->assertSame('BetaTech', config('mail.from.name'));
    }

    public function test_legacy_tls_and_ssl_schemes_are_upgraded_on_read(): void
    {
        Setting::query()->insert([
            ['key' => 'mail_mailer', 'value' => 'smtp'],
            ['key' => 'mail_encryption', 'value' => 'tls'],
        ]);

        (new MailSettings)->apply();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));

        Setting::query()->where('key', 'mail_encryption')->update(['value' => 'ssl']);
        (new MailSettings)->apply();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_saving_with_blank_password_keeps_the_stored_secret(): void
    {
        $owner = User::factory()->create();
        app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->actingAs($owner);

        Livewire::test(Settings::class)
            ->set('mailer', 'smtp')
            ->set('host', 'smtp.betatech.co')
            ->set('password', 'super-secret')
            ->set('fromAddress', 'hello@betatech.co')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(Settings::class)
            ->set('mailer', 'smtp')
            ->set('host', 'smtp2.betatech.co')
            ->set('fromAddress', 'hello@betatech.co')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('super-secret', app(MailSettings::class)->get('mail_password'));
        $this->assertSame('smtp2.betatech.co', app(MailSettings::class)->get('mail_host'));
    }

    public function test_pending_invitation_shows_copyable_accept_link(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $invitation = $workspace->invitations()->create([
            'email' => 'sharmin.salma@betatech.co',
            'role' => 'admin',
            'token' => 'copy-link-token-123',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($owner)
            ->get("/app/{$workspace->slug}/team")
            ->assertOk()
            ->assertSee('Copy link')
            ->assertSee($invitation->token);
    }

    public function test_invitation_survives_a_mail_failure(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->actingAs($owner);

        Filament::setTenant($workspace);

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(
            new \RuntimeException('Connection could not be established with host'),
        );

        Livewire::test(Team::class)
            ->set('inviteEmail', 'sharmin.salma@betatech.co')
            ->set('inviteRole', 'admin')
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'sharmin.salma@betatech.co',
            'role' => 'admin',
        ]);
    }
}
