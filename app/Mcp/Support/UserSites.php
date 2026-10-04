<?php

namespace App\Mcp\Support;

use App\Actions\ResolveVisibleSites;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;

/**
 * Every site an MCP caller may act on: the visible sites of each workspace
 * they belong to. MCP requests carry no Filament tenant context, so
 * workspace visibility is resolved here with the same rules the dashboard
 * uses (workspace admins see everything, members see open or assigned
 * projects).
 */
class UserSites
{
    /**
     * @return Collection<int, Site>
     */
    public function __invoke(User $user): Collection
    {
        return $user->workspaces()->get()
            ->flatMap(
                fn (Workspace $workspace): Collection => app(ResolveVisibleSites::class)($workspace, $user)
                    ->with(['inventory', 'project'])
                    ->orderBy('name')
                    ->get(),
            )
            ->values();
    }
}
