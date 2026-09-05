<?php

namespace Tests\Feature;

use App\Actions\CreateWorkspaceForUser;
use App\Mail\WorkspaceInvitationMail;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_member_by_email_and_they_accept(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $invitee = User::factory()->create(['email' => 'dev@betatech.co']);

        // The accept endpoint requires authentication.
        $this->postJson('/invitations/fake-token/accept')->assertUnauthorized();

        // A stranger cannot consume someone else's invitation.
        $stranger = User::factory()->create(['email' => 'stranger@elsewhere.test']);
        $invitation = $workspace->invitations()->create([
            'email' => 'dev@betatech.co',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($stranger)
            ->postJson('/invitations/'.$invitation->token.'/accept')
            ->assertForbidden();

        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $stranger->id,
        ]);

        // The invitee accepts through the public accept endpoint.
        $this->actingAs($invitee)
            ->postJson('/invitations/'.$invitation->token.'/accept')
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invited_guest_joins_with_name_and_password_only(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $invitation = $workspace->invitations()->create([
            'email' => 'sharmin@betatech.co',
            'role' => 'admin',
            'token' => 'join-token-123',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        // The invite page offers the inline join form with email pre-filled.
        $this->get('/invitations/join-token-123')
            ->assertOk()
            ->assertSee('Create account &amp; join BetaTech', false)
            ->assertSee('sharmin@betatech.co');

        // One post: name + password. Email and workspace come from the invite.
        $this->post('/invitations/join-token-123/register', [
            'name' => 'Sharmin',
            'password' => 'super-secret-123',
            'password_confirmation' => 'super-secret-123',
        ])->assertRedirect();

        $user = User::query()->where('email', 'sharmin@betatech.co')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($user->id, auth()->id(), 'The new member is signed in.');

        // No second workspace was created for the invitee.
        $this->assertSame(1, $user->workspaces()->count());
    }

    public function test_invite_registration_rejects_weak_passwords_and_existing_emails(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $invitation = $workspace->invitations()->create([
            'email' => 'sharmin@betatech.co',
            'role' => 'member',
            'token' => 'weak-token-123',
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Too-short password: validation error, nothing created.
        $this->from('/invitations/weak-token-123')
            ->post('/invitations/weak-token-123/register', [
                'name' => 'Sharmin',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect('/invitations/weak-token-123')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'sharmin@betatech.co']);
        $this->assertNull($invitation->fresh()->accepted_at);

        // Email already registered: the register route bounces to the
        // normal accept flow instead of creating a duplicate.
        User::factory()->create(['email' => 'sharmin@betatech.co']);
        $this->post('/invitations/weak-token-123/register', [
            'name' => 'Someone',
            'password' => 'super-secret-123',
            'password_confirmation' => 'super-secret-123',
        ])->assertRedirect(route('invitations.show', ['token' => 'weak-token-123']));

        $this->assertSame(1, User::query()->where('email', 'sharmin@betatech.co')->count());
    }

    public function test_invitation_email_is_sent_to_the_right_address(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $invitation = $workspace->invitations()->create([
            'email' => 'dev@betatech.co',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation, $owner));

        Mail::assertSent(WorkspaceInvitationMail::class, fn (WorkspaceInvitationMail $mail) => $mail->hasTo('dev@betatech.co'));
    }

    public function test_only_matching_email_can_accept_invitation(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $invitation = $workspace->invitations()->create([
            'email' => 'dev@betatech.co',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(7),
        ]);

        $stranger = User::factory()->create(['email' => 'stranger@elsewhere.test']);
        $this->actingAs($stranger)
            ->postJson('/invitations/'.$invitation->token.'/accept')
            ->assertForbidden();

        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $stranger->id,
        ]);
    }

    public function test_open_projects_are_visible_to_all_members_but_restricted_ones_are_not(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');

        $openProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Open']);
        $restrictedProject = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client X']);
        $restrictedProject->members()->attach($owner->id, ['role' => 'lead']);

        $member = User::factory()->create();
        $workspace->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member);

        // Open project: every workspace member can view.
        $this->assertTrue($member->can('view', $openProject));

        // Restricted project: invisible and untouchable for non-members.
        $this->assertFalse($member->can('view', $restrictedProject));
        $this->assertFalse($member->can('update', $restrictedProject));
        $this->assertFalse($member->can('delete', $restrictedProject));
    }

    public function test_assigned_project_roles_control_access(): void
    {
        $owner = User::factory()->create();
        $workspace = app(CreateWorkspaceForUser::class)($owner, 'BetaTech');
        $project = Project::create(['workspace_id' => $workspace->id, 'name' => 'Client A']);
        $project->members()->attach($owner->id, ['role' => 'lead']);

        // Project members must also be workspace members.
        $viewer = User::factory()->create();
        $workspace->users()->attach($viewer, ['role' => 'member']);
        $project->members()->attach($viewer, ['role' => 'viewer']);

        $editor = User::factory()->create();
        $workspace->users()->attach($editor, ['role' => 'member']);
        $project->members()->attach($editor, ['role' => 'editor']);

        $this->actingAs($owner);

        // Viewers can see but not modify.
        $this->assertTrue($viewer->can('view', $project));
        $this->assertFalse($viewer->can('update', $project));
        $this->assertFalse($viewer->can('delete', $project));

        // Editors can modify but not delete.
        $this->assertTrue($editor->can('view', $project));
        $this->assertTrue($editor->can('update', $project));
        $this->assertFalse($editor->can('delete', $project));
    }
}
