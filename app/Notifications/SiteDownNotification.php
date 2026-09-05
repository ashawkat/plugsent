<?php

namespace App\Notifications;

use App\Models\Site;
use App\Models\UptimeIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteDownNotification extends Notification
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
            subject: '🔴 '.$this->site->name.' is down',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $detail = $this->incident->last_error
            ? 'Last error: '.$this->incident->last_error
            : 'Last HTTP status: '.($this->incident->last_status_code ?? 'unknown');

        return (new MailMessage)
            ->error()
            ->subject('🔴 '.$this->site->name.' is down')
            ->greeting($this->site->name.' is not responding')
            ->line('Plugsent could not reach **'.$this->site->url.'** on consecutive checks.')
            ->line($detail)
            ->line('Monitoring keeps checking — you will get one email the moment it recovers.')
            ->action('Open in Plugsent', $this->siteUrl());
    }

    public function siteUrl(): string
    {
        return url('/app/'.$this->site->workspace->slug.'/sites/'.$this->site->getKey());
    }
}
