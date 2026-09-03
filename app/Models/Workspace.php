<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'owner_id'])]
class Workspace extends Model
{
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workspace) {
            $workspace->slug ??= static::generateUniqueSlug($workspace->name);
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';

        $slug = $base;
        $attempt = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$attempt;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
