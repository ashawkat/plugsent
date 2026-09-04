<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workspace_id', 'project_id', 'name', 'url', 'status', 'php_version', 'wp_version', 'last_seen_at', 'tags', 'capabilities', 'connector_version', 'api_key', 'api_key_hash'])]
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
            'api_key' => 'encrypted',
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

    public function updateExclusions(): HasMany
    {
        return $this->hasMany(UpdateExclusion::class);
    }

    /**
     * Whether updates for this inventory item are excluded on the platform
     * side (no Update button, skipped by "Update all").
     */
    public function isExcludedFromUpdates(string $context, string $slug): bool
    {
        return $this->updateExclusions()
            ->where('context', $context)
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * Whether the site's connector advertises this command capability.
     * Capabilities are refreshed on every poll, so sites running an old
     * connector keep unsupported actions out of the UI automatically.
     */
    public function supportsCommand(string $type): bool
    {
        return in_array($type, (array) ($this->capabilities ?? []), true);
    }

    public function pendingUpdatesCount(): int
    {
        return $this->inventory()->where('update_available', true)->count();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function generateApiKey(): string
    {
        $key = 'plsk_'.bin2hex(random_bytes(20));

        $this->forceFill([
            'api_key' => $key,
            'api_key_hash' => hash('sha256', $key),
        ])->save();

        return $key;
    }

    public function ensureApiKey(): string
    {
        if (filled($this->api_key) && filled($this->api_key_hash)) {
            return $this->api_key;
        }

        return $this->generateApiKey();
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now(), 'status' => 'connected'])->save();
    }
}
