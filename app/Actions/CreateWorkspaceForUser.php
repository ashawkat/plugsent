<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workspace;

class CreateWorkspaceForUser
{
    public function __invoke(User $user, string $name): Workspace
    {
        $workspace = Workspace::create([
            'name' => $name,
            'owner_id' => $user->getKey(),
        ]);

        $workspace->users()->attach($user, ['role' => 'owner']);

        return $workspace;
    }
}
