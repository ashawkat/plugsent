<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Resources\Sites\Pages\ViewSite;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateExclusion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SiteActionsTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_CAPABILITIES = [
        'inventory.get',
        'update.run',
        'admin.login',
        'plugin.activate',
        'plugin.deactivate',
        'plugin.delete',
        'theme.activate',
        'theme.delete',
    ];

    public function test_request_action_dispatches_whitelisted_commands(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteFor($owner, capabilities: self::FULL_CAPABILITIES);
        $this->actingAs($owner);
        Filament::setTenant($site->workspace);

        Livewire::test(ViewSite::class, ['record' => $site])
            ->call('requestAction', 'plugin.activate', 'hello-dolly')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_commands', [
            'site_id' => $site->id,
            'type' => 'plugin.activate',
            'status' => SiteCommand::STATUS_PENDING,
        ]);

        $command = SiteCommand::query()->latest('id')->first();
        $this->assertSame('plugin', $command->payload['context']);
        $this->assertSame('hello-dolly', $command->payload['slug']);
        $this->assertNotNull($command->batch_id, 'Each action gets its own batch for the inventory refresh.');

        // Non-whitelisted types are dropped, never queued.
        $before = SiteCommand::query()->count();
        Livewire::test(ViewSite::class, ['record' => $site])
            ->call('requestAction', 'plugin.nuke', 'hello-dolly');

        $this->assertSame($before, SiteCommand::query()->count());
    }

    public function test_remote_deletes_need_the_site_delete_ability(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteFor($owner, capabilities: self::FULL_CAPABILITIES);

        $editor = User::factory()->create();
        $site->workspace->users()->attach($editor, ['role' => 'member']);
        $site->project->members()->attach($editor, ['role' => 'editor']);
        $this->actingAs($editor);
        Filament::setTenant($site->workspace);

        // Editors may activate…
        Livewire::test(ViewSite::class, ['record' => $site])
            ->call('requestAction', 'plugin.activate', 'hello-dolly')
            ->assertHasNoErrors();

        // …but remote deletes are gated to admins and project leads.
        $this->assertTrue(
            Gate::forUser($editor)->denies('delete', $site),
            'Project editors must not hold the site delete ability.',
        );
        $this->assertSame(
            0,
            SiteCommand::query()->where('type', 'plugin.delete')->count(),
        );

        // Owners (workspace admins) can delete.
        $this->actingAs($owner);
        Livewire::test(ViewSite::class, ['record' => $site])
            ->call('requestAction', 'plugin.delete', 'hello-dolly')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_commands', [
            'site_id' => $site->id,
            'type' => 'plugin.delete',
        ]);
    }

    public function test_actions_are_hidden_for_sites_whose_connector_lacks_them(): void
    {
        $owner = User::factory()->create();

        $oldConnector = $this->siteFor($owner, capabilities: ['inventory.get', 'update.run']);
        $newConnector = $this->siteFor($owner, capabilities: self::FULL_CAPABILITIES);

        foreach ([$oldConnector, $newConnector] as $site) {
            InventoryItem::query()->create([
                'site_id' => $site->id, 'context' => InventoryItem::CONTEXT_PLUGIN,
                'slug' => 'hello-dolly', 'name' => 'Hello Dolly', 'version' => '1.7',
                'update_available' => false, 'update_version' => null, 'active' => true,
            ]);
            InventoryItem::query()->create([
                'site_id' => $site->id, 'context' => InventoryItem::CONTEXT_THEME,
                'slug' => 'twentytwentyfour', 'name' => 'Twenty Twenty-Four', 'version' => '1.2',
                'update_available' => false, 'update_version' => null, 'active' => false,
            ]);
        }

        $this->actingAs($owner);

        // Excluding from updates is platform-side and needs no connector
        // support, so its toggle shows for every connected site. Only the
        // management buttons are capability-gated.
        $this->get($this->viewUrl($oldConnector))
            ->assertOk()
            ->assertDontSee('Deactivate')
            ->assertSee('Exclude updates');

        // Filament keeps the current tenant in the session, so follow the
        // real user path: open the workspace, then the site page.
        $this->get('/app/'.$newConnector->workspace->slug)->assertOk();

        $this->get($this->viewUrl($newConnector))
            ->assertOk()
            ->assertSee('Deactivate')
            ->assertSee('Exclude updates');
    }

    public function test_update_category_skips_excluded_items(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteFor($owner, capabilities: self::FULL_CAPABILITIES);
        $this->actingAs($owner);
        Filament::setTenant($site->workspace);

        InventoryItem::query()->create([
            'site_id' => $site->id, 'context' => InventoryItem::CONTEXT_PLUGIN,
            'slug' => 'akismet', 'name' => 'Akismet', 'version' => '1.0',
            'update_available' => true, 'update_version' => '1.1', 'active' => true,
        ]);
        InventoryItem::query()->create([
            'site_id' => $site->id, 'context' => InventoryItem::CONTEXT_PLUGIN,
            'slug' => 'hello-dolly', 'name' => 'Hello Dolly', 'version' => '1.7',
            'update_available' => true, 'update_version' => '1.8', 'active' => false,
        ]);

        $component = Livewire::test(ViewSite::class, ['record' => $site]);
        $component->call('toggleUpdateExclusion', 'plugin', 'hello-dolly');

        $this->assertDatabaseHas('update_exclusions', [
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'hello-dolly',
        ]);

        $component->call('updateCategory', 'plugin');

        $queued = SiteCommand::query()
            ->where('site_id', $site->id)
            ->where('type', 'update.run')
            ->pluck('payload');
        $this->assertCount(1, $queued, 'Only the non-excluded item is queued.');
        $this->assertSame('akismet', $queued->first()['slug']);

        // Toggling again clears the exclusion.
        $component->call('toggleUpdateExclusion', 'plugin', 'hello-dolly');
        $this->assertSame(0, UpdateExclusion::query()->count());
    }

    public function test_site_helpers_report_capability_and_exclusion(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteFor($owner, capabilities: ['inventory.get']);

        $this->assertTrue($site->supportsCommand('inventory.get'));
        $this->assertFalse($site->supportsCommand('plugin.activate'));
        $this->assertFalse($site->isExcludedFromUpdates('plugin', 'akismet'));

        UpdateExclusion::query()->create([
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'akismet', 'created_at' => now(),
        ]);

        $this->assertTrue($site->isExcludedFromUpdates('plugin', 'akismet'));
    }

    private function siteFor(User $owner, array $capabilities = []): Site
    {
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Client A main',
            'url' => 'https://client-a.test',
            'status' => 'connected',
            'capabilities' => $capabilities,
        ]);
    }

    private function viewUrl(Site $site): string
    {
        return '/app/'.$site->workspace->slug.'/sites/'.$site->getKey();
    }
}
