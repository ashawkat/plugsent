<?php

namespace App\Support;

use App\Models\SiteCommand;

class CommandSubject
{
    /**
     * Human description of a site command, used by the site's in-flight
     * progress panel, the History tab, and the fleet activity feed.
     */
    public function format(SiteCommand $command): string
    {
        $slug = (string) ($command->payload['slug'] ?? '');
        $key = (string) ($command->payload['key'] ?? '');

        return match ($command->type) {
            'update.run' => 'Updating · '.$slug,
            'update.safe' => 'Safe updating · '.$slug,
            'restore.apply' => 'Restoring · '.$slug,
            'inventory.get' => 'Refreshing inventory',
            'security.scan' => 'Security scan',
            'security.harden' => (($command->payload['enable'] ?? false) ? 'Hardening · ' : 'Reverting · ').($key !== '' ? $key : $slug),
            'plugin.activate' => 'Activating · '.$slug,
            'plugin.deactivate' => 'Deactivating · '.$slug,
            'plugin.delete' => 'Deleting plugin · '.$slug,
            'theme.activate' => 'Switching theme · '.$slug,
            'theme.delete' => 'Deleting theme · '.$slug,
            default => $command->type,
        };
    }
}
