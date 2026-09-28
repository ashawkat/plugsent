<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Vulnerability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitesListTest extends TestCase
{
    use RefreshDatabase;

    public function test_sites_list_groups_by_project_and_orders_by_risk(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Acme');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Clients']);

        $healthy = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Good Shop',
            'url' => 'https://good.test',
            'status' => 'connected',
            'security_score' => 92,
            'uptime_enabled' => true,
            'uptime_status' => 'up',
        ]);

        $stale = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Stale Blog',
            'url' => 'https://stale.test',
            'status' => 'pending',
            'security_score' => 55,
        ]);

        $plugin = InventoryItem::query()->create([
            'site_id' => $stale->id,
            'context' => InventoryItem::CONTEXT_PLUGIN,
            'slug' => 'woocommerce',
            'name' => 'WooCommerce',
            'version' => '9.3.1',
            'update_available' => false,
            'active' => true,
        ]);
        $plugin->forceFill(['vuln_count' => 1])->save();

        Vulnerability::query()->create([
            'source' => Vulnerability::SOURCE_WORDFENCE,
            'external_id' => 'list-test-1',
            'software_type' => 'plugin',
            'software_slug' => 'woocommerce',
            'title' => 'WooCommerce reflected XSS',
            'cvss' => 9.1,
            'patched_version' => '9.4.2',
        ]);

        $this->actingAs($owner);

        $response = $this->get("/app/{$workspace->slug}/sites");

        $response->assertOk();

        // Grouped by project, disconnected site first (risk ordering).
        $response->assertSeeInOrder(['Clients', 'Stale Blog', 'Good Shop']);

        // Fleet summary strip: the (lazy) KPI widget is mounted above the
        // table; its rendered numbers are covered by DashboardTest.
        $response->assertSee('FleetStatsWidget');

        // Severity dots and the 30-day mini strip render inline.
        $response->assertSee('plugsent-sev-critical');
        $response->assertSee('1 critical');
        $response->assertSee('plugsent-tstrip');

        unset($healthy);
    }
}
