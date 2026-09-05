<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of one safe-update pipeline run on a site: restore point
 * coverage, smoke-test outcome, and whether a rollback happened.
 */
class UpdateRun extends Model
{
    public const STATUS_UPDATED = 'updated';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id', 'context', 'slug', 'command_id', 'from_version', 'to_version',
        'status', 'message', 'smoke_ok', 'smoke_status_code', 'db_backup', 'files_backup',
    ];

    protected function casts(): array
    {
        return [
            'smoke_ok' => 'boolean',
            'smoke_status_code' => 'integer',
            'db_backup' => 'boolean',
            'files_backup' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(SiteCommand::class, 'command_id');
    }
}
