<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Pages\ConnectSite;
use App\Models\PairingCode;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_site_form_creates_site_and_issues_pairing_code(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $this->actingAs($owner);

        Filament::setTenant($workspace);

        Livewire::test(ConnectSite::class)
            ->fillForm([
                'name' => 'BT Demo',
                'url' => 'https://demo.betatech.co/',
                'project_id' => $project->id,
            ])
            ->call('connect')
            ->assertHasNoFormErrors()
            ->assertSee('Finish pairing on the WordPress site')
            ->assertSee('PLSG-');

        $site = $workspace->sites()->where('name', 'BT Demo')->first();

        $this->assertNotNull($site, 'The site should have been created from the form.');
        $this->assertSame('https://demo.betatech.co/', $site->url);
        $this->assertSame($workspace->id, $site->workspace_id);
        $this->assertSame('pending', $site->status);
        $this->assertCount(1, PairingCode::query()->where('site_id', $site->id)->get());
    }

    public function test_connect_site_requires_all_fields(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $this->actingAs($owner);

        Filament::setTenant($workspace);

        Livewire::test(ConnectSite::class)
            ->call('connect')
            ->assertHasFormErrors(['name', 'url', 'project_id']);

        $this->assertDatabaseCount('sites', 0);
    }
}
