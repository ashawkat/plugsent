<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Mcp\Servers\PlugsentServer;
use App\Mcp\Tools\GetSiteStatus;
use App\Mcp\Tools\ListPendingUpdates;
use App\Mcp\Tools\ListSites;
use App\Mcp\Tools\RescanInventory;
use App\Mcp\Tools\UpdateSite;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\UpdateExclusion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Contracts\HasAbilities;
use Tests\TestCase;

class McpGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_endpoint_requires_authentication(): void
    {
        $this->postJson('/mcp/plugsent', $this->initializeRequest())
            ->assertStatus(401);
    }

    public function test_mcp_endpoint_authenticates_sanctum_bearer_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('MCP access', ['mcp'])->plainTextToken;

        $this->postJson('/mcp/plugsent', $this->initializeRequest(), [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertSee('plugsent');
    }

    public function test_server_exposes_the_five_plugsent_tools(): void
    {
        PlugsentServer::tools()->assertRegistered([
            ListSites::class,
            GetSiteStatus::class,
            ListPendingUpdates::class,
            UpdateSite::class,
            RescanInventory::class,
        ]);
    }

    public function test_list_sites_only_shows_sites_the_caller_can_see(): void
    {
        [$owner, $member, $workspace, $visibleSite, $hiddenSite] = $this->workspaceWithVisibleAndHiddenSites();

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))->tool(ListSites::class)
            ->assertSee($visibleSite->url)
            ->assertSee($hiddenSite->url);

        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))->tool(ListSites::class)
            ->assertSee($visibleSite->url)
            ->assertDontSee($hiddenSite->url);
    }

    public function test_get_site_status_reports_score_failing_checks_and_updates(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
            'security_score' => 50,
            'security_facts' => ['xmlrpc_enabled' => true, 'disallow_file_edit' => true],
            'security_scanned_at' => now(),
        ]);

        InventoryItem::create([
            'site_id' => $site->id,
            'context' => 'plugin',
            'slug' => 'akismet',
            'name' => 'Akismet',
            'version' => '5.2',
            'update_available' => true,
            'update_version' => '5.3',
            'active' => true,
        ]);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))->tool(GetSiteStatus::class, ['site_id' => $site->id])
            ->assertSee('Security score: 50/100')
            ->assertSee('XML-RPC disabled')
            ->assertSee('1 plugin');
    }

    public function test_get_site_status_rejects_invisible_sites(): void
    {
        [$owner, , , , $hiddenSite] = $this->workspaceWithVisibleAndHiddenSites();

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))->tool(ListSites::class)->assertSee($hiddenSite->url);

        $member = User::factory()->create();
        $hiddenSite->workspace->users()->attach($member, ['role' => 'member']);

        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))->tool(GetSiteStatus::class, ['site_id' => $hiddenSite->id])
            ->assertSee('No site with that id is visible to this account.');
    }

    public function test_update_site_queues_safe_updates_and_skips_excluded_items(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
            'capabilities' => ['update.safe'],
        ]);

        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);
        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'hello-dolly',
            'name' => 'Hello Dolly', 'version' => '1.7', 'update_available' => true,
            'update_version' => '1.8', 'active' => true,
        ]);
        UpdateExclusion::create(['site_id' => $site->id, 'context' => 'plugin', 'slug' => 'hello-dolly']);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(UpdateSite::class, ['site_id' => $site->id, 'context' => 'plugin'])
            ->assertSee('Queued 1 plugin update(s) for client-a.test')
            ->assertSee('restore point, smoke test, and automatic rollback');

        $command = SiteCommand::query()->sole();
        $this->assertSame('update.safe', $command->type);
        $this->assertSame('akismet', $command->payload['slug']);
        $this->assertNotNull($command->batch_id);
        $this->assertSame(SiteCommand::STATUS_PENDING, $command->status);
    }

    public function test_update_site_uses_the_plain_update_for_core_even_when_safe_updates_exist(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
            'capabilities' => ['update.safe'],
        ]);

        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'core', 'slug' => 'wordpress',
            'name' => 'WordPress', 'version' => '6.7', 'update_available' => true,
            'update_version' => '6.8', 'active' => true,
        ]);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(UpdateSite::class, ['site_id' => $site->id, 'context' => 'core'])
            ->assertSee('Queued 1 core update(s)');

        $this->assertSame('update.run', SiteCommand::query()->sole()->type);
    }

    public function test_update_site_rejects_members_without_update_permission(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);

        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);

        // The project is open (no members), so a plain workspace member can
        // see the site — but only leads/editors and workspace admins may
        // update it, and the MCP tool must enforce the same policy.
        $member = User::factory()->create();
        $workspace->users()->attach($member, ['role' => 'member']);

        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))
            ->tool(UpdateSite::class, ['site_id' => $site->id, 'context' => 'plugin'])
            ->assertSee('You do not have permission to update this site.');

        $this->assertSame(0, SiteCommand::query()->count());
    }

    public function test_rescan_inventory_queues_connected_sites_the_caller_may_update(): void
    {
        [$owner, , $workspace, $visibleSite, $hiddenSite] = $this->workspaceWithVisibleAndHiddenSites();
        $offline = $this->makeSite($workspace, $visibleSite->project, 'disconnected');

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class)
            ->assertSee('Queued 2 inventory rescans: ['.$visibleSite->getKey().'] '.$visibleSite->name.', ['.$hiddenSite->getKey().'] '.$hiddenSite->name)
            ->assertDontSee($offline->name);

        $commands = SiteCommand::query()->where('type', 'inventory.get')->get();
        $this->assertCount(2, $commands);
        $this->assertSame(SiteCommand::STATUS_PENDING, $commands->first()->status);
    }

    public function test_rescan_inventory_skips_sites_beyond_update_permission(): void
    {
        [$owner, $member] = $this->workspaceWithVisibleAndHiddenSites();

        // The member can see the open project's site but may not update it,
        // and cannot see the restricted project's site at all.
        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class)
            ->assertSee('Nothing to rescan: no connected sites are available to you right now.');

        $this->assertSame(0, SiteCommand::query()->count());
    }

    public function test_rescan_inventory_with_site_id_targets_only_that_site(): void
    {
        [$owner, , $workspace, $visibleSite] = $this->workspaceWithVisibleAndHiddenSites();
        $other = $this->makeSite($workspace, $visibleSite->project);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class, ['site_id' => $other->id])
            ->assertSee('Queued 1 inventory rescan: ['.$other->getKey().'] '.$other->name);

        $command = SiteCommand::query()->sole();
        $this->assertSame('inventory.get', $command->type);
        $this->assertSame($other->id, $command->site_id);
    }

    public function test_rescan_inventory_rejects_invisible_sites(): void
    {
        [$owner, $member, $workspace, $visibleSite, $hiddenSite] = $this->workspaceWithVisibleAndHiddenSites();

        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class, ['site_id' => $hiddenSite->id])
            ->assertSee('No site with that id is visible to this account.');

        // A member can see an open project's site but may not update it.
        PlugsentServer::actingAs($this->withAbilities($member, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class, ['site_id' => $visibleSite->id])
            ->assertSee('You do not have permission to rescan this site.');

        $this->assertSame(0, SiteCommand::query()->count());
    }

    public function test_rescan_inventory_reports_offline_single_site(): void
    {
        [$owner, , $workspace, $visibleSite] = $this->workspaceWithVisibleAndHiddenSites();
        $offline = $this->makeSite($workspace, $visibleSite->project, 'disconnected');

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(RescanInventory::class, ['site_id' => $offline->id])
            ->assertSee($offline->name.' is not connected right now');

        $this->assertSame(0, SiteCommand::query()->count());
    }

    public function test_read_only_token_cannot_queue_updates_or_rescans(): void
    {
        [$owner, , , $visibleSite] = $this->workspaceWithVisibleAndHiddenSites();
        InventoryItem::create([
            'site_id' => $visibleSite->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read']))
            ->tool(UpdateSite::class, ['site_id' => $visibleSite->id, 'context' => 'plugin'])
            ->assertSee('This token is read-only');

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read']))
            ->tool(RescanInventory::class, ['site_id' => $visibleSite->id])
            ->assertSee('This token is read-only');

        $this->assertSame(0, SiteCommand::query()->count());
    }

    public function test_read_only_token_can_still_read_pending_updates(): void
    {
        [$owner, , , $visibleSite] = $this->workspaceWithVisibleAndHiddenSites();
        InventoryItem::create([
            'site_id' => $visibleSite->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read']))
            ->tool(ListPendingUpdates::class)
            ->assertSee('[plugin] Akismet 5.2 → 5.3');
    }

    public function test_mcp_commands_carry_the_source_and_actor_for_the_audit_trail(): void
    {
        [$owner, , , $visibleSite] = $this->workspaceWithVisibleAndHiddenSites();
        InventoryItem::create([
            'site_id' => $visibleSite->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(UpdateSite::class, ['site_id' => $visibleSite->id, 'context' => 'plugin'])
            ->assertSee('Queued 1 plugin update(s)');

        $command = SiteCommand::query()->sole();
        $this->assertSame('mcp', $command->source);
        $this->assertStringContainsString($owner->email, $command->actor);
    }

    public function test_list_pending_updates_marks_excluded_items(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
        ]);

        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'plugin', 'slug' => 'akismet',
            'name' => 'Akismet', 'version' => '5.2', 'update_available' => true,
            'update_version' => '5.3', 'active' => true,
        ]);
        InventoryItem::create([
            'site_id' => $site->id, 'context' => 'theme', 'slug' => 'astra',
            'name' => 'Astra', 'version' => '4.8', 'update_available' => true,
            'update_version' => '4.9', 'active' => true,
        ]);
        UpdateExclusion::create(['site_id' => $site->id, 'context' => 'theme', 'slug' => 'astra']);

        PlugsentServer::actingAs($this->withAbilities($owner, ['mcp:read', 'mcp:write']))
            ->tool(ListPendingUpdates::class)
            ->assertSee('2 item(s)')
            ->assertSee('[plugin] Akismet 5.2 → 5.3')
            ->assertSee('[theme] Astra 4.8 → 4.9 (excluded — update-site skips it)');
    }

    /**
     * A workspace where an open project's site is visible to everyone and a
     * member-restricted project's site is visible to the owner only.
     *
     * @return array{0: User, 1: User, 2: Workspace, 3: Site, 4: Site}
     */
    private function workspaceWithVisibleAndHiddenSites(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $workspace->users()->attach($member, ['role' => 'member']);

        $openProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $visibleSite = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $openProject->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);

        $restrictedProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Secret']);
        $restrictedProject->members()->attach($owner, ['role' => 'lead']);
        $hiddenSite = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $restrictedProject->id,
            'name' => 'secret-site.test',
            'url' => 'https://secret-site.test',
            'status' => 'connected',
        ]);

        return [$owner, $member, $workspace, $visibleSite, $hiddenSite];
    }

    private function makeSite(Workspace $workspace, Project $project, string $status = 'connected'): Site
    {
        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'extra-site.test',
            'url' => 'https://extra-site.test',
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Give the user a stand-in Sanctum token carrying exactly these
     * abilities for the tool call (the guard normally supplies one).
     */
    private function withAbilities($user, array $abilities)
    {
        return $user->withAccessToken(new class($abilities) implements HasAbilities
        {
            public function __construct(public readonly array $abilities) {}

            public function can($ability, ...$params)
            {
                return in_array($ability, $this->abilities, true) || in_array('*', $this->abilities, true);
            }

            public function cant($ability, ...$params)
            {
                return ! $this->can($ability);
            }
        });
    }

    private function initializeRequest(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'Test Client', 'version' => '1.0'],
            ],
        ];
    }
}
