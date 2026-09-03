<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // listings are scoped to the current tenant by Filament
    }

    public function view(User $user, Project $project): bool
    {
        return $user->belongsToWorkspace($project->workspace);
    }

    public function create(User $user): bool
    {
        return true; // creation happens inside the user's current workspace
    }

    public function update(User $user, Project $project): bool
    {
        return $user->belongsToWorkspace($project->workspace);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->belongsToWorkspace($project->workspace);
    }
}
