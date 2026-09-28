<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Filament\Widgets\FleetChecksWidget;
use App\Filament\Widgets\FleetHealthWidget;
use App\Filament\Widgets\FleetStatsWidget;
use App\Filament\Widgets\FleetUptimeWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\SiteActivityWidget;
use App\Models\FleetSnapshot;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\User;
use App\Models\Vulnerability;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A workspace with one healthy site and one that needs attention:
     * disconnected, PHP 8.1, core behind, and a critical WooCommerce
     * vulnerability. Average score: (92 + 55) / 2 = 74.
     *
     * @return array{0: User, 1: Workspace, 2: Site, 3: Site}
     */
    private function seedFleet(): array
    {
        $owner = User::factory()->create(['name' => 'Ashraf Owner']);
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Acme');
        $openProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Clients']);
        $guardedProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Internal']);

        $good = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $openProject->id,
            'name' => 'Good Shop',
            'url' => 'https://good.test',
            'status' => 'connected',
            'php_version' => '8.4',
            'wp_version' => '6.8.1',
            'security_score' => 92,
            'security_scanned_at' => now()->subHour(),
            'uptime_enabled' => true,
            'uptime_status' => 'up',
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $bad = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $guardedProject->id,
            'name' => 'Stale Blog',
            'url' => 'https://stale.test',
            'status' => 'pending',
            'php_version' => '8.1',
            'wp_version' => '6.7.2',
            'security_score' => 55,
            'security_scanned_at' => now()->subHour(),
            'last_seen_at' => now()->subDays(6),
        ]);

        InventoryItem::query()->create([
            'site_id' => $good->id,
            'context' => InventoryItem::CONTEXT_CORE,
            'slug' => 'wp-core',
            'name' => 'WordPress',
            'version' => '6.8.1',
            'update_available' => false,
            'active' => true,
        ]);

        InventoryItem::query()->create([
            'site_id' => $bad->id,
            'context' => InventoryItem::CONTEXT_CORE,
            'slug' => 'wp-core',
            'name' => 'WordPress',
            'version' => '6.7.2',
            'update_available' => true,
            'update_version' => '6.8.1',
            'active' => true,
        ]);

        // vuln_count is written by the vulnerability matcher, not part of
        // the model's fillable attributes — set it the same way. The update
        // to 9.4.2 is also the fix for the vulnerability below.
        $plugin = InventoryItem::query()->create([
            'site_id' => $bad->id,
            'context' => InventoryItem::CONTEXT_PLUGIN,
            'slug' => 'woocommerce',
            'name' => 'WooCommerce',
            'version' => '9.3.1',
            'update_available' => true,
            'update_version' => '9.4.2',
            'active' => true,
        ]);
        $plugin->forceFill(['vuln_count' => 1])->save();

        Vulnerability::query()->create([
            'source' => Vulnerability::SOURCE_WORDFENCE,
            'external_id' => 'test-woocommerce-1',
            'software_type' => 'plugin',
            'software_slug' => 'woocommerce',
            'title' => 'WooCommerce reflected XSS',
            'cvss' => 9.1,
            'patched_version' => '9.4.2',
        ]);

        SiteCommand::query()->create([
            'site_id' => $bad->id,
            'type' => 'update.safe',
            'payload' => ['context' => 'plugin', 'slug' => 'woocommerce'],
            'status' => SiteCommand::STATUS_COMPLETED,
            'result' => [],
            'dispatched_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
        ]);

        return [$owner, $workspace, $good, $bad];
    }

    public function test_owner_dashboard_renders_fleet_widgets(): void
    {
        [$owner, $workspace] = $this->seedFleet();

        $this->actingAs($owner);

        // The greeting widget is not lazy, so its content is on the page.
        $this->get("/app/{$workspace->slug}")
            ->assertOk()
            ->assertSee('Ashraf Owner');

        Filament::setTenant($workspace);

        Livewire::test(FleetStatsWidget::class)
            ->assertSee('Pending updates')
            ->assertSee('Sites online')
            ->assertSee('Open vulnerabilities')
            ->assertSee('1 / 2');

        Livewire::test(FleetHealthWidget::class)
            ->assertSee('74')
            ->assertSee('of 14');

        Livewire::test(FleetChecksWidget::class)
            ->assertSee('HTTPS enabled');

        Livewire::test(NeedsAttentionWidget::class)
            ->assertSee('Stale Blog')
            ->assertSee('Disconnected')
            ->assertSee('WordPress core behind')
            ->assertSee('1 critical vulnerability');

        Livewire::test(SiteActivityWidget::class)
            ->assertSee('Safe updating · woocommerce');

        Livewire::test(FleetUptimeWidget::class)
            ->assertSee('Good Shop');
    }

    public function test_member_dashboard_excludes_restricted_project_sites(): void
    {
        [, $workspace, $good, $bad] = $this->seedFleet();

        $member = User::factory()->create();
        $workspace->users()->attach($member, ['role' => 'member']);

        // The guarded project gets an explicit member, so the plain member
        // no longer sees the "bad" site inside it (the open project with
        // the healthy site stays visible).
        $lead = User::factory()->create();
        $bad->project->members()->attach($lead, ['role' => 'lead']);

        $this->actingAs($member);
        Filament::setTenant($workspace);

        Livewire::test(FleetStatsWidget::class)
            ->assertSee('1 / 1')
            ->assertDontSee('Stale Blog');

        Livewire::test(NeedsAttentionWidget::class)
            ->assertDontSee('Stale Blog')
            ->assertSee('Every site is connected');

        unset($good);
    }

    public function test_site_overview_leads_with_risk_and_actions(): void
    {
        [, $workspace, , $bad] = $this->seedFleet();

        // Connect the problem site and give it the facts a real scan reports,
        // so the upgraded Overview tab has failing checks, quick wins,
        // pending updates, and uptime facts to render.
        $bad->forceFill([
            'status' => 'connected',
            'last_seen_at' => now()->subMinutes(4),
            'capabilities' => ['inventory.get', 'update.run', 'update.safe', 'security.scan', 'security.harden'],
            'security_facts' => [
                'wp_debug' => false,
                'php_version' => '8.1',
                'blog_public' => true,
                'inactive_plugins' => 3,
                'inactive_themes' => 1,
                'xmlrpc_enabled' => true,
                'disallow_file_edit' => false,
            ],
            'hardening' => [],
            'ssl_expires_at' => now()->addMonths(6),
            'domain_expires_at' => now()->addMonths(4),
        ])->save();

        $owner = User::where('name', 'Ashraf Owner')->first();
        $this->actingAs($owner);

        $this->get("/app/{$workspace->slug}/sites/{$bad->id}?tab=overview")
            ->assertOk()
            // Score ring + passing count.
            ->assertSee('plugsent-ring')
            ->assertSee('checks passing')
            // Failing checks with reasons.
            ->assertSee('PHP version receives security fixes')
            ->assertSee('WordPress core up to date')
            // Quick wins: 6 failing checks are hardening-fixable → +43 pts.
            ->assertSee('Quick wins')
            ->assertSee('+43 pts')
            // Pending updates with versions and a per-context Update all.
            ->assertSee('WooCommerce')
            ->assertSee('9.3.1')
            ->assertSee('Update core (1)')
            ->assertSee('Update plugins (1)')
            // Tab badges for pending updates.
            ->assertSee('plugsent-badge-warn')
            // Uptime strip and expiry facts.
            ->assertSee('uptime over 30 days')
            ->assertSee('SSL expires');
    }

    public function test_snapshot_command_records_one_row_per_workspace_per_day(): void
    {
        [$owner, $workspace] = $this->seedFleet();

        $this->actingAs($owner);

        $this->artisan('plugsent:snapshot-fleet')->assertSuccessful();

        $this->assertDatabaseHas('fleet_snapshots', [
            'workspace_id' => $workspace->id,
            'snapshot_date' => now()->toDateString(),
            'sites_total' => 2,
            'sites_connected' => 1,
            'updates_pending' => 2,
            'vulns_open' => 1,
            'vulns_critical' => 1,
        ]);

        // Running again the same day updates in place instead of stacking.
        $this->artisan('plugsent:snapshot-fleet')->assertSuccessful();

        $this->assertSame(1, FleetSnapshot::query()->where('workspace_id', $workspace->id)->count());
    }
}
