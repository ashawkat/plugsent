<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_id', 'site_key', 'secret', 'status'])]
class SiteCredential extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
