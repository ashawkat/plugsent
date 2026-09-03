<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_touch_other_workspaces_data(): void
    {
        $ownerA = User::factory()->create();
        $workspaceA = app(CreateWorkspaceForUser::class)($ownerA, 'Alpha');
        $projectA = Project::create(['workspace_id' => $workspaceA->id, 'name' => 'Client A']);
        $siteA = Site::create([
            'workspace_id' => $workspaceA->id,
            'project_id' => $projectA->id,
            'name' => 'client-a.test',
            'url' => 'https://client-a.test',
        ]);

        $ownerB = User::factory()->create();
        $workspaceB = app(CreateWorkspaceForUser::class)($ownerB, 'Beta');

        $this->assertFalse($ownerB->canAccessTenant($workspaceA));
        $this->assertFalse($ownerB->can('view', $projectA));
        $this->assertFalse($ownerB->can('update', $projectA));
        $this->assertFalse($ownerB->can('delete', $projectA));
        $this->assertFalse($ownerB->can('view', $siteA));
        $this->assertFalse($ownerB->can('update', $siteA));
        $this->assertFalse($ownerB->can('delete', $siteA));

        $this->assertTrue($ownerA->can('view', $projectA));
        $this->assertTrue($ownerA->can('update', $projectA));
        $this->assertTrue($ownerA->can('view', $siteA));
        $this->assertTrue($ownerA->can('update', $siteA));
        $this->assertTrue($ownerA->can('delete', $siteA));
    }

    public function test_site_belongs_to_exactly_one_project_inside_its_workspace(): void
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

        $this->assertTrue($site->project->is($project));
        $this->assertTrue($site->workspace->is($workspace));
        $this->assertSame(1, $workspace->sites()->count());
    }
}
