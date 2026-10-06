<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Actions\ProcessInventoryResult;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_version_update_offer_is_not_stored_as_an_update(): void
    {
        $site = $this->site();

        app(ProcessInventoryResult::class)($site, [
            'core' => [
                ['slug' => 'wordpress', 'name' => 'WordPress', 'version' => '7.1.3', 'update_available' => true, 'update_version' => '7.1.3', 'active' => true],
            ],
            'plugins' => [],
            'themes' => [],
        ]);

        $core = InventoryItem::query()->where('site_id', $site->id)->where('context', 'core')->first();

        $this->assertNotNull($core);
        $this->assertSame('7.1.3', $core->version);
        $this->assertFalse((bool) $core->update_available, 'A same-version offer must not count as an update.');
    }

    public function test_older_version_offers_are_dropped_but_real_updates_survive(): void
    {
        $site = $this->site();

        app(ProcessInventoryResult::class)($site, [
            'core' => [
                ['slug' => 'wordpress', 'name' => 'WordPress', 'version' => '7.1.3', 'update_available' => true, 'update_version' => '7.1.2', 'active' => true],
                ['slug' => 'wordpress-old', 'name' => 'WordPress Old', 'version' => '7.1.3', 'update_available' => true, 'update_version' => '7.1.4', 'active' => true],
            ],
            'plugins' => [],
            'themes' => [],
        ]);

        $items = InventoryItem::query()->where('site_id', $site->id)->orderBy('slug')->get();

        $this->assertFalse((bool) $items->firstWhere('slug', 'wordpress')->update_available, 'A downgrade offer is not an update.');
        $this->assertTrue((bool) $items->firstWhere('slug', 'wordpress-old')->update_available, 'A real upgrade stays an update.');
    }

    private function site(): Site
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'Alpha');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        return Site::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
            'status' => 'connected',
        ]);
    }
}
