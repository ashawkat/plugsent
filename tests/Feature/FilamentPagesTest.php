<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_render_resource_pages(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);

        $this->actingAs($owner);

        $this->get("/app/{$workspace->slug}")->assertOk();
        $this->get("/app/{$workspace->slug}/projects")->assertOk();
        $this->get("/app/{$workspace->slug}/projects/create")->assertOk();
        $this->get("/app/{$workspace->slug}/sites")->assertOk();
        $this->get("/app/{$workspace->slug}/sites/create")->assertOk();
        $this->get("/app/{$workspace->slug}/connect-site")->assertOk();
    }

    public function test_non_member_cannot_render_tenant_pages(): void
    {
        $ownerA = User::factory()->create();
        $workspaceA = app(CreateWorkspaceForUser::class)($ownerA, 'Alpha');

        $intruder = User::factory()->create();
        app(CreateWorkspaceForUser::class)($intruder, 'Beta');
        $this->actingAs($intruder);

        // Filament's tenancy middleware 404s tenants the user can't access,
        // which also avoids leaking that the workspace exists.
        $this->get("/app/{$workspaceA->slug}")->assertNotFound();
    }
}
