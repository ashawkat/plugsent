<?php

namespace App\Notifications;

use App\Models\Site;
use App\Models\UptimeIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteRecoveredNotification extends Notification
{
    use Queueable;

    public function __construct(public Site $site, public UptimeIncident $incident) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function envelope(object $notifiable): Envelope
    {
        return new Envelope(
            subject: '🟢 '.$this->site->name.' is back up',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $downFor = $this->incident->started_at->diffForHumans($this->incident->ended_at, ['parts' => 2]);

        return (new MailMessage)
            ->subject('🟢 '.$this->site->name.' is back up')
            ->greeting($this->site->name.' recovered')
            ->line('**'.$this->site->url.'** is responding again.')
            ->line('It was down for '.$downFor.' ('.$this->incident->failure_count.' failed checks).')
            ->action('Open in Plugsent', url('/app/'.$this->site->workspace->slug.'/sites/'.$this->site->getKey()));
    }
}
