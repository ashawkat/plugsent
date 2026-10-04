<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Actions\EnqueueSiteCommand;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_rescans_for_connected_sites_only(): void
    {
        [$connected, $offline] = $this->makeSites();

        $this->artisan('plugsent:refresh-inventory')->assertSuccessful();

        $command = SiteCommand::query()->sole();
        $this->assertSame($connected->id, $command->site_id);
        $this->assertSame('inventory.get', $command->type);
        $this->assertSame(SiteCommand::STATUS_PENDING, $command->status);
        $this->assertNull(SiteCommand::query()->where('site_id', $offline->id)->first());
    }

    public function test_skips_sites_with_an_outstanding_inventory_request(): void
    {
        [$connected] = $this->makeSites();

        app(EnqueueSiteCommand::class)($connected, 'inventory.get');
        SiteCommand::query()->update(['status' => SiteCommand::STATUS_DISPATCHED]);

        $this->artisan('plugsent:refresh-inventory')->assertSuccessful();

        // Only the pre-existing dispatched command remains — nothing stacked.
        $this->assertSame(1, SiteCommand::query()->count());
    }

    public function test_queues_again_once_the_outstanding_request_settles(): void
    {
        [$connected] = $this->makeSites();

        app(EnqueueSiteCommand::class)($connected, 'inventory.get');
        SiteCommand::query()->update(['status' => SiteCommand::STATUS_COMPLETED]);

        $this->artisan('plugsent:refresh-inventory')->assertSuccessful();

        $this->assertSame(2, SiteCommand::query()->where('type', 'inventory.get')->count());
    }

    /**
     * A workspace with one connected site and one offline site.
     *
     * @return array{0: Site, 1: Site}
     */
    private function makeSites(): array
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        $connected = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);

        $offline = Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-b.test',
            'url' => 'https://client-b.test',
            'status' => 'disconnected',
        ]);

        return [$connected, $offline];
    }
}
