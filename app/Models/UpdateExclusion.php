<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform-side "exclude from updates" flag for one inventory item. The
 * connector has no auto-updates, so exclusion means: no Update button, no
 * "Update all" batch, and it is the safe-update pipeline's future denylist.
 */
class UpdateExclusion extends Model
{
    public $timestamps = false;

    protected $fillable = ['site_id', 'context', 'slug', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
