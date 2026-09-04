<?php

namespace App\Filament\Pages;

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Team extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Team';

    protected string $view = 'filament.pages.team';

    public Workspace $workspace;

    public ?string $inviteEmail = null;

    public ?string $inviteRole = 'member';

    public function mount(): void
    {
        $this->workspace = Filament::getTenant();
    }

    public function getTitle(): string
    {
        return 'Team — '.$this->workspace->name;
    }

    public function canManageTeam(): bool
    {
        return auth()->user()->isWorkspaceAdmin($this->workspace);
    }

    public function members()
    {
        return $this->workspace->users()->orderBy('workspace_user.created_at')->get();
    }

    public function pendingInvitations()
    {
        return $this->workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();
    }

    public function invite(): void
    {
        $this->authorizeManagement();

        $email = strtolower(trim((string) $this->inviteEmail));
        $role = in_array($this->inviteRole, ['admin', 'member']) ? $this->inviteRole : 'member';

        $validator = Validator::make(
            ['email' => $email],
            [
                'email' => [
                    'required', 'email', 'max:255',
                    Rule::unique('workspace_invitations')->where('workspace_id', $this->workspace->getKey()),
                    Rule::notIn([$this->workspace->owner->email]),
                ],
            ],
            ['email.unique' => 'An invitation for this email is already pending.', 'email.not_in' => 'This member already owns the workspace.'],
        );

        if ($validator->fails()) {
            Notification::make()->title($validator->errors()->first())->danger()->send();

            return;
        }

        $invitation = $this->workspace->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new WorkspaceInvitationMail($invitation, auth()->user()));

        $this->inviteEmail = null;
        $this->inviteRole = 'member';

        Notification::make()
            ->title('Invitation sent')
            ->body("{$email} has 7 days to accept.")
            ->success()
            ->send();
    }

    public function revokeInvitation(int $invitation): void
    {
        $this->authorizeManagement();

        WorkspaceInvitation::query()
            ->whereKey($invitation)
            ->where('workspace_id', $this->workspace->getKey())
            ->first()?->delete();

        Notification::make()->title('Invitation revoked')->success()->send();
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->authorizeManagement();

        abort_unless(in_array($role, ['admin', 'member']), 422);

        $this->workspace->users()->updateExistingPivot($userId, ['role' => $role]);

        Notification::make()->title('Role updated')->success()->send();
    }

    public function removeMember(int $userId): void
    {
        $this->authorizeManagement();

        abort_if($userId === auth()->id(), 422);
        abort_if($this->workspace->owner_id === $userId, 422);

        $member = User::findOrFail($userId);
        $this->workspace->users()->detach($member->id);

        Notification::make()
            ->title('Member removed')
            ->body("{$member->name} no longer has access to this workspace.")
            ->success()
            ->send();
    }

    protected function authorizeManagement(): void
    {
        abort_unless(auth()->user()->isWorkspaceAdmin($this->workspace), 403);
    }
}
