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
        return $user->belongsToWorkspace($site->workspace);
    }

    public function create(User $user): bool
    {
        return true; // creation happens inside the user's current workspace
    }

    public function update(User $user, Site $site): bool
    {
        return $user->belongsToWorkspace($site->workspace);
    }

    public function delete(User $user, Site $site): bool
    {
        return $user->belongsToWorkspace($site->workspace);
    }
}
