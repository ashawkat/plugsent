<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\UpdatesAvailableNotification;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpdatesDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed clock keeps the due-time check deterministic.
        $this->travelTo(Carbon::create(2026, 10, 5, 8, 30));
        app(AppSettings::class)->put(AppSettings::UPDATES_DIGEST_TIME, '08:00');
    }

    public function test_sends_one_daily_email_after_the_configured_time(): void
    {
        Notification::fake();
        [$owner, $admin, $site] = $this->workspaceWithSite();

        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        Notification::assertSentTo([$owner, $admin], UpdatesAvailableNotification::class);

        // A second run the same day must not repeat the email.
        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        $this->assertSame(1, Notification::sent($owner, UpdatesAvailableNotification::class)->count());
    }

    public function test_does_not_fire_before_the_configured_time(): void
    {
        Notification::fake();
        [$owner, , $site] = $this->workspaceWithSite();

        $this->travelTo(Carbon::create(2026, 10, 5, 7, 45));
        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_honors_a_custom_send_time(): void
    {
        Notification::fake();
        [$owner, , $site] = $this->workspaceWithSite();
        app(AppSettings::class)->put(AppSettings::UPDATES_DIGEST_TIME, '09:00');

        $this->travelTo(Carbon::create(2026, 10, 5, 8, 50));
        $this->artisan('plugsent:updates-digest')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(Carbon::create(2026, 10, 5, 9, 15));
        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        Notification::assertSentTo($owner, UpdatesAvailableNotification::class);
    }

    public function test_fires_again_the_next_day(): void
    {
        Notification::fake();
        [$owner, , $site] = $this->workspaceWithSite();

        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        $this->travelTo(Carbon::create(2026, 10, 6, 8, 30));
        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        $this->assertSame(2, Notification::sent($owner, UpdatesAvailableNotification::class)->count());
    }

    public function test_skips_workspaces_with_nothing_pending(): void
    {
        Notification::fake();
        [$owner, , $site] = $this->workspaceWithSite();

        // The helper's akismet row has an update; mark it applied so the
        // workspace has nothing pending.
        InventoryItem::query()->where('site_id', $site->id)->update(['update_available' => false]);

        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_respects_the_per_user_updates_opt_out(): void
    {
        Notification::fake();
        [$owner, $admin] = $this->workspaceWithSite();
        $admin->forceFill(['email_preferences' => ['updates' => false]])->save();

        $this->artisan('plugsent:updates-digest')->assertSuccessful();

        Notification::assertSentTo($owner, UpdatesAvailableNotification::class);
        Notification::assertNotSentTo($admin, UpdatesAvailableNotification::class);
    }

    /**
     * A workspace with one connected site that has a pending plugin update.
     *
     * @return array{0: User, 1: User, 2: Site}
     */
    private function workspaceWithSite(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $workspace->users()->attach($admin, ['role' => 'admin']);

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

        return [$owner, $admin, $site];
    }
}
