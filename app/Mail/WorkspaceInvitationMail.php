<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to join '.$this->invitation->workspace->name.' on Plugsent',
        );
    }

    public function content(): Content
    {
        // Row values render unescaped in the shared template, so any
        // user-entered text goes through e() here.
        return new Content(
            view: 'mail.plugsent',
            with: [
                'bannerLabel' => 'Workspace invitation',
                'title' => 'Join '.$this->invitation->workspace->name.' on Plugsent',
                'intro' => [
                    e($this->inviterName).' invited you to join <strong>'.e($this->invitation->workspace->name).'</strong> as <strong>'.e($this->invitation->role).'</strong>.',
                    'Plugsent is their self-hosted WordPress fleet manager — plugin, theme, and core updates safely managed from one dashboard.',
                ],
                'rows' => [
                    ['label' => 'Workspace', 'value' => e($this->invitation->workspace->name)],
                    ['label' => 'Invited by', 'value' => e($this->inviterName)],
                    ['label' => 'Role', 'value' => e($this->invitation->role)],
                ],
                'buttonUrl' => route('invitations.show', ['token' => $this->invitation->token]),
                'buttonText' => 'Accept invitation',
                'footNote' => 'This invitation link is personal to your email address. Manage email preferences in your Plugsent profile.',
            ],
        );
    }
}
