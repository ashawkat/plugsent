<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // listings are scoped to the current tenant by Filament
    }

    public function view(User $user, Site $site): bool
    {
        return $user->canAccessProject($site->project);
    }

    public function create(User $user): bool
    {
        return true; // creation happens inside the user's current workspace
    }

    public function update(User $user, Site $site): bool
    {
        return $this->manage($user, $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->destroy($user, $site);
    }

    private function manage(User $user, Site $site): bool
    {
        if ($user->isWorkspaceAdmin($site->workspace)) {
            return true;
        }

        $role = $user->projectRole($site->project);

        return in_array($role, ['lead', 'editor'], true);
    }

    private function destroy(User $user, Site $site): bool
    {
        if ($user->isWorkspaceAdmin($site->workspace)) {
            return true;
        }

        return $user->projectRole($site->project) === 'lead';
    }
}
