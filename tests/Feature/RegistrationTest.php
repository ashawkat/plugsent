<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Auth\Pages\Register;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_loads(): void
    {
        $response = $this->get('/app/register');

        $response->assertOk();
    }

    public function test_panel_requires_authentication(): void
    {
        $response = $this->get('/app');

        $response->assertRedirect('/app/login');
    }

    public function test_creating_a_workspace_assigns_slug_and_owner_role(): void
    {
        $user = User::factory()->create();

        $workspace = app(CreateWorkspaceForUser::class)($user, 'BetaTech');

        $this->assertSame('betatech', $workspace->slug);
        $this->assertTrue($workspace->owner->is($user));
        $this->assertTrue($user->belongsToWorkspace($workspace));
        $this->assertSame('owner', $workspace->users->find($user)->pivot->role);
        $this->assertTrue($user->canAccessTenant($workspace));
    }

    public function test_workspace_slugs_are_unique(): void
    {
        $first = Workspace::create(['name' => 'BetaTech', 'owner_id' => User::factory()->create()->id]);
        $second = Workspace::create(['name' => 'BetaTech', 'owner_id' => User::factory()->create()->id]);

        $this->assertSame('betatech', $first->slug);
        $this->assertSame('betatech-2', $second->slug);
    }

    public function test_signup_through_the_form_creates_user_and_workspace(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Tom',
                'workspaceName' => 'BetaTech',
                'email' => 'tom@betatech.test',
                'password' => 'super-secret-123',
                'passwordConfirmation' => 'super-secret-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'tom@betatech.test')->firstOrFail();
        $workspace = Workspace::query()->where('slug', 'betatech')->firstOrFail();

        $this->assertTrue($workspace->owner->is($user));
        $this->assertSame('owner', $user->workspaces->find($workspace)->pivot->role);
    }
}
