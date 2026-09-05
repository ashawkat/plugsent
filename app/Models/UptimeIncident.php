<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One downtime episode of a site: opened after two consecutive failed
 * checks, closed by the first healthy one.
 */
class UptimeIncident extends Model
{
    protected $fillable = [
        'site_id', 'started_at', 'ended_at', 'last_status_code',
        'last_error', 'failure_count', 'down_notified',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'down_notified' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
