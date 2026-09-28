<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single source of truth for which sites a user may see in a
 * workspace. Workspace admins and owners see everything; regular members
 * only see sites in projects that are open or explicitly assigned to them.
 */
class ResolveVisibleSites
{
    /**
     * Sites visible to the given user in the workspace.
     *
     * @return Builder<Site>
     */
    public function __invoke(Workspace $workspace, ?User $user = null): Builder
    {
        $query = Site::query()->where('workspace_id', $workspace->getKey());

        if ($user === null || $user->isWorkspaceAdmin($workspace)) {
            return $query;
        }

        return $this->restrictToMemberProjects($query, $user);
    }

    /**
     * Restrict any site query (already scoped to a workspace) to open
     * projects or projects the user belongs to.
     *
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public function restrictToMemberProjects(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $p) => $p->where(
            fn (Builder $q) => $q->whereDoesntHave('members')
                ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->getKey())),
        ));
    }
}
