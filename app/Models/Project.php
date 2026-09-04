<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'name', 'description'])]
class Project extends Model
{
    protected function casts(): array
    {
        return [
            'workspace_id' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Projects start open to the whole workspace. The moment members are
     * explicitly assigned, access narrows to those members (+ workspace
     * owners/admins).
     */
    public function isRestrictedToMembers(): bool
    {
        return $this->members()->exists();
    }
}
