<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->belongsToWorkspace($tenant);
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->workspaces;
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->whereKey($workspace)->exists();
    }

    public function workspaceRole(Workspace $workspace): ?string
    {
        $role = $this->workspaces()
            ->whereKey($workspace)
            ->value('workspace_user.role');

        return $role !== null ? (string) $role : null;
    }

    public function hasWorkspaceRole(Workspace $workspace, array $roles): bool
    {
        return in_array($this->workspaceRole($workspace), $roles, true);
    }

    public function isWorkspaceAdmin(Workspace $workspace): bool
    {
        return $this->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }

    /**
     * Workspace admins/owners see everything. Regular members see a project
     * when it has no explicit members (open by default) or when they are
     * assigned to it.
     */
    public function canAccessProject(Project $project): bool
    {
        if (! $this->belongsToWorkspace($project->workspace)) {
            return false;
        }

        if ($this->isWorkspaceAdmin($project->workspace)) {
            return true;
        }

        if ($project->isRestrictedToMembers()) {
            return $project->members()->whereKey($this->getKey())->exists();
        }

        return true;
    }

    public function projectRole(Project $project): ?string
    {
        return $project->members()->whereKey($this->getKey())->value('project_user.role');
    }
}
