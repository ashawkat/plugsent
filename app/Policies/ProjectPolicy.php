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
        return $user->canAccessProject($project);
    }

    public function create(User $user): bool
    {
        return true; // creation happens inside the user's current workspace
    }

    public function update(User $user, Project $project): bool
    {
        return $this->manage($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->destroy($user, $project);
    }

    private function manage(User $user, Project $project): bool
    {
        if ($user->isWorkspaceAdmin($project->workspace)) {
            return true;
        }

        $role = $user->projectRole($project);

        return in_array($role, ['lead', 'editor'], true);
    }

    private function destroy(User $user, Project $project): bool
    {
        if ($user->isWorkspaceAdmin($project->workspace)) {
            return true;
        }

        return $user->projectRole($project) === 'lead';
    }
}
