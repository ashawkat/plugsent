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
        return new Content(
            markdown: 'mail.workspace-invitation',
            with: [
                'workspaceName' => $this->invitation->workspace->name,
                'role' => $this->invitation->role,
                'acceptUrl' => route('invitations.show', ['token' => $this->invitation->token]),
                'inviterName' => $this->inviter->name,
            ],
        );
    }
}
