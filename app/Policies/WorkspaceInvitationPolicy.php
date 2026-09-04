<?php

namespace App\Policies;

use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Auth\User as Authenticatable;

class WorkspaceInvitationPolicy
{
    public function accept(Authenticatable $user, WorkspaceInvitation $invitation): bool
    {
        return strcasecmp($user->email, $invitation->email) === 0;
    }
}
