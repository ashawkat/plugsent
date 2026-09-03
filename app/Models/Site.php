<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workspace_id', 'project_id', 'name', 'url', 'status', 'php_version', 'wp_version', 'last_seen_at', 'tags', 'capabilities'])]
class Site extends Model
{
    protected function casts(): array
    {
        return [
            'workspace_id' => 'integer',
            'project_id' => 'integer',
            'last_seen_at' => 'datetime',
            'tags' => 'array',
            'capabilities' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function credential(): HasOne
    {
        return $this->hasOne(SiteCredential::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function pendingUpdatesCount(): int
    {
        return $this->inventory()->where('update_available', true)->count();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now(), 'status' => 'connected'])->save();
    }
}
