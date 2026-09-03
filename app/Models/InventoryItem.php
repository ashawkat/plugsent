<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_id', 'context', 'slug', 'name', 'version', 'update_available', 'update_version', 'active'])]
class InventoryItem extends Model
{
    public const CONTEXT_CORE = 'core';

    public const CONTEXT_PLUGIN = 'plugin';

    public const CONTEXT_THEME = 'theme';

    protected function casts(): array
    {
        return [
            'update_available' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
