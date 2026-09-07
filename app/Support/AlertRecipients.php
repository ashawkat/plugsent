<?php

namespace App\Support;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who gets which alert email: workspace owners/admins, filtered by each
 * user's per-category email preference from their profile.
 */
class AlertRecipients
{
    public static function for(Site $site, string $pref): Collection
    {
        return $site->workspace->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get()
            ->filter(fn (User $user) => $user->wantsEmail($pref))
            ->values();
    }
}
