<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Console\Commands\UptimeCheck;
use App\Models\Project;
use App\Models\Site;
use App\Models\UptimeIncident;
use App\Models\User;
use App\Notifications\SiteDownNotification;
use App\Notifications\SiteRecoveredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UptimeCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_check_marks_site_up_without_incident(): void
    {
        $site = $this->siteWithUrl();
        Http::fake([$site->url => Http::response('hello', 200)]);

        $this->artisan(UptimeCheck::class);

        $site->refresh();
        $this->assertSame(Site::UPTIME_UP, $site->uptime_status);
        $this->assertSame(200, $site->uptime_last_status_code);
        $this->assertNotNull($site->uptime_last_checked_at);
        $this->assertSame(0, UptimeIncident::query()->count());
    }

    public function test_incident_opens_after_two_consecutive_failures(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteWithUrl(owner: $owner);
        Notification::fake();

        Http::fake([$site->url => Http::sequence()
            ->push('server error', 500)
            ->push('server error', 500)]);

        // First failure: recorded, but not yet an incident.
        $this->artisan(UptimeCheck::class);
        $site->refresh();
        $this->assertSame(Site::UPTIME_DOWN, $site->uptime_status);
        $this->assertSame(1, $site->uptime_consecutive_failures);
        $this->assertSame(0, UptimeIncident::query()->count());

        // Second failure: incident opens, workspace is notified once.
        $this->travel(6)->minutes();
        $this->artisan(UptimeCheck::class);

        $incident = UptimeIncident::query()->first();
        $this->assertNotNull($incident);
        $this->assertTrue($incident->isActive());
        $this->assertSame(500, $incident->last_status_code);
        $this->assertSame(2, $incident->failure_count);

        Notification::assertSentTo($owner, SiteDownNotification::class, 1);
    }

    public function test_recovery_closes_incident_and_notifies_once(): void
    {
        $owner = User::factory()->create();
        $site = $this->siteWithUrl(owner: $owner);
        Notification::fake();

        Http::fake([$site->url => Http::sequence()
            ->push('error', 500)
            ->push('error', 500)
            ->push('hello', 200)]);
        $this->artisan(UptimeCheck::class);
        $this->travel(6)->minutes();
        $this->artisan(UptimeCheck::class);

        $incident = UptimeIncident::query()->first();
        $this->assertNotNull($incident);

        $this->travel(6)->minutes();
        $this->artisan(UptimeCheck::class);

        $incident->refresh();
        $this->assertFalse($incident->isActive());
        $this->assertNotNull($incident->ended_at);

        $site->refresh();
        $this->assertSame(Site::UPTIME_UP, $site->uptime_status);
        $this->assertSame(0, $site->uptime_consecutive_failures);

        Notification::assertSentTo($owner, SiteRecoveredNotification::class, 1);
        Notification::assertSentTo($owner, SiteDownNotification::class, 1);
    }

    public function test_staging_basic_auth_counts_as_up(): void
    {
        $site = $this->siteWithUrl();
        Http::fake([$site->url => Http::response('auth required', 401)]);

        $this->artisan(UptimeCheck::class);

        $site->refresh();
        $this->assertSame(Site::UPTIME_UP, $site->uptime_status);
    }

    public function test_paused_and_disconnected_sites_are_not_checked(): void
    {
        $paused = $this->siteWithUrl();
        $paused->forceFill(['uptime_enabled' => false])->save();

        $pending = $this->siteWithUrl();
        $pending->forceFill(['status' => 'pending'])->save();

        Http::fake();

        $this->artisan(UptimeCheck::class);

        $this->assertNull($paused->fresh()->uptime_last_checked_at);
        $this->assertNull($pending->fresh()->uptime_last_checked_at);
    }

    private function siteWithUrl(?User $owner = null): Site
    {
        $owner ??= User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'Client A main',
            'url' => 'https://client-'.$project->id.'.test',
            'status' => 'connected',
        ]);
    }
}
