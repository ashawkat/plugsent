<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function __construct() {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function envelope(object $notifiable): Envelope
    {
        return new Envelope(
            subject: 'Your Plugsent password was changed',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->view('mail.plugsent', [
                'bannerColor' => '#16a34a',
                'bannerLabel' => 'Security',
                'title' => 'Your password was changed',
                'intro' => [
                    'The password for <strong>'.$notifiable->email.'</strong> was just changed.',
                    'If this was you, no action is needed. If you did not change it, someone else may '
                    .'have access to your account — reset your password immediately and contact your '
                    .'workspace admin.',
                ],
                'buttonUrl' => url('/app/login'),
                'buttonText' => 'Sign in',
                'footNote' => 'You receive this whenever your Plugsent password changes.',
            ]);
    }
}
