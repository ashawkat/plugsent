<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Pages\Profile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use Tests\TestCase;

class PasskeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_offers_passkey_sign_in_and_support_link(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('Sign in with a passkey')
            ->assertSee(route('passkey.login-options'))
            ->assertSee('https://ko-fi.com/adnanshawkat');
    }

    public function test_dashboard_footer_links_to_kofi(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->actingAs($owner);

        $this->get("/app/{$workspace->slug}")
            ->assertOk()
            ->assertSee('https://ko-fi.com/adnanshawkat');
    }

    public function test_login_options_are_guest_only_and_use_discoverable_credentials(): void
    {
        $this->get('/passkeys/login/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge']]);

        $this->assertNotNull(session('passkey.verification_options'));

        // A signed-in user has no business in the login ceremony.
        $this->actingAs(User::factory()->create());
        $this->get('/passkeys/login/options')->assertRedirect();
    }

    public function test_registration_options_require_authentication_and_bind_the_user(): void
    {
        // The management routes are API-ish: guests get a 401, not a redirect.
        $this->get('/user/passkeys/options')->assertUnauthorized();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->get('/user/passkeys/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge', 'user' => ['id', 'name']]]);

        // The options are stashed for the follow-up POST /user/passkeys,
        // which replays them through the WebAuthn ceremony.
        $this->assertNotNull(session('passkey.registration_options'));
    }

    public function test_store_endpoint_rejects_guests_and_invalid_payloads(): void
    {
        $this->postJson('/user/passkeys', ['name' => 'Phone'])->assertUnauthorized();

        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->postJson('/user/passkeys', ['name' => 'Phone'])
            ->assertJsonValidationErrors(['credential']);

        $this->assertSame(0, $owner->passkeys()->count());
    }

    public function test_profile_page_lists_registered_passkeys(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->registerPasskey($owner, 'Office MacBook');
        $this->actingAs($owner);

        $this->get("/app/{$workspace->slug}/profile")
            ->assertOk()
            ->assertSee('Passkeys')
            ->assertSee('Office MacBook')
            ->assertSee('Add passkey');
    }

    public function test_removing_a_passkey_requires_the_current_password(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $passkey = $this->registerPasskey($owner, 'Office MacBook');
        $this->actingAs($owner);
        Filament::setTenant($workspace);

        // No password and a wrong password both refuse, and the passkey stays.
        Livewire::test(Profile::class)
            ->call('deletePasskey', $passkey->id)
            ->assertHasErrors(['passkeyPassword']);

        Livewire::test(Profile::class)
            ->set('passkeyPassword', 'not-my-password')
            ->call('deletePasskey', $passkey->id)
            ->assertHasErrors(['passkeyPassword']);

        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);

        Livewire::test(Profile::class)
            ->set('passkeyPassword', 'password')
            ->call('deletePasskey', $passkey->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    /**
     * A stored passkey stands in for a completed WebAuthn registration
     * ceremony, which needs a real authenticator and cannot run here.
     */
    private function registerPasskey(User $user, string $name): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => 'plugsent-test-'.md5($name),
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);
    }
}
