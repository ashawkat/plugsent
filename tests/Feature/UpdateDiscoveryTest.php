<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Actions\ProcessInventoryResult;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Notifications\UpdateDiscoveryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpdateDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_scan_with_updates_notifies_workspace_admins(): void
    {
        Notification::fake();
        [$owner, $admin, $member, $site] = $this->workspaceWithSite();

        $this->scanSite($site, [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')]);

        Notification::assertSentTo([$owner, $admin], UpdateDiscoveryNotification::class);
        Notification::assertNotSentTo($member, UpdateDiscoveryNotification::class);

        $fresh = $site->fresh();
        $this->assertNotNull($fresh->updates_fingerprint);
        $this->assertNotNull($fresh->updates_notified_at);
    }

    public function test_identical_scan_does_not_repeat_the_email(): void
    {
        Notification::fake();
        [$owner, , , $site] = $this->workspaceWithSite();
        $inventory = [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')];

        $this->scanSite($site, $inventory);
        $this->scanSite($site, $inventory);

        $this->assertSame(1, Notification::sent($owner, UpdateDiscoveryNotification::class)->count());
    }

    public function test_changed_update_set_emails_again(): void
    {
        Notification::fake();
        [$owner, , , $site] = $this->workspaceWithSite();

        $this->scanSite($site, [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')]);
        $this->scanSite($site, [
            $this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3'),
            $this->pluginUpdate('hello-dolly', 'Hello Dolly', '1.7', '1.8'),
        ]);

        $this->assertSame(2, Notification::sent($owner, UpdateDiscoveryNotification::class)->count());
    }

    public function test_clean_scan_resets_so_new_updates_email_again(): void
    {
        Notification::fake();
        [$owner, , , $site] = $this->workspaceWithSite();
        $akismet = [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')];

        $this->scanSite($site, $akismet);
        $this->scanSite($site, []);

        $fresh = $site->fresh();
        $this->assertNull($fresh->updates_fingerprint);
        $this->assertSame(1, Notification::sent($owner, UpdateDiscoveryNotification::class)->count());

        $this->scanSite($site, $akismet);

        $this->assertSame(2, Notification::sent($owner, UpdateDiscoveryNotification::class)->count());
        $this->assertNotNull($site->fresh()->updates_fingerprint);
    }

    public function test_recipients_who_opted_out_of_update_emails_receive_nothing(): void
    {
        Notification::fake();
        [$owner, $admin, , $site] = $this->workspaceWithSite();
        $admin->forceFill(['email_preferences' => ['updates' => false]])->save();

        $this->scanSite($site, [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')]);

        Notification::assertSentTo($owner, UpdateDiscoveryNotification::class);
        Notification::assertNotSentTo($admin, UpdateDiscoveryNotification::class);
    }

    public function test_discovery_email_renders_the_site_link_as_html(): void
    {
        Notification::fake();
        [$owner, , , $site] = $this->workspaceWithSite();

        $this->scanSite($site, [$this->pluginUpdate('akismet', 'Akismet', '5.2', '5.3')]);

        $notification = Notification::sent($owner, UpdateDiscoveryNotification::class)->first();
        $html = $notification->toMail($owner)->render();

        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringNotContainsString('&lt;a href=', $html);
        $this->assertStringContainsString('Google Sans', $html);
    }

    /**
     * Run a connector-style inventory result through the processing action.
     *
     * @param  array<int, array<string, mixed>>  $plugins
     */
    private function scanSite(Site $site, array $plugins): void
    {
        app(ProcessInventoryResult::class)($site, [
            'core' => null,
            'plugins' => $plugins,
            'themes' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pluginUpdate(string $slug, string $name, string $version, string $updateVersion): array
    {
        return [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'update_available' => true,
            'update_version' => $updateVersion,
            'active' => true,
        ];
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Site}
     */
    private function workspaceWithSite(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $workspace->users()->attach($admin, ['role' => 'admin']);
        $workspace->users()->attach($member, ['role' => 'member']);

        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $site = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);

        return [$owner, $admin, $member, $site];
    }
}
