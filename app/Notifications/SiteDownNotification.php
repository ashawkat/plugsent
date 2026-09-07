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
            ? \Illuminate\Support\Str::limit($this->incident->last_error, 120)
            : 'HTTP '.$this->incident->last_status_code.' after '.$this->incident->failure_count.' failed checks';

        return (new MailMessage)
            ->subject('🔴 '.$this->site->name.' is down')
            ->view('mail.plugsent', [
                'bannerColor' => '#dc2626',
                'bannerLabel' => 'Site down',
                'title' => $this->site->name.' is not responding',
                'intro' => [
                    'Plugsent could not reach <strong>'.$this->site->url.'</strong> on consecutive checks.',
                    'An incident is ongoing — you will get a recovery email the moment it is back.',
                ],
                'rows' => [
                    ['label' => 'Site', 'value' => $this->site->name],
                    ['label' => 'URL', 'value' => $this->site->url],
                    ['label' => 'Down since', 'value' => $this->incident->started_at->format('M j, H:i').' ('.$this->incident->started_at->diffForHumans().')'],
                    ['label' => 'Last failure', 'value' => $detail],
                ],
                'buttonUrl' => $this->siteUrl().'?tab=uptime',
                'buttonText' => 'Open uptime details',
                'footNote' => 'You receive this because you are a workspace admin and the site has uptime monitoring enabled.',
            ]);
    }

    public function siteUrl(): string
    {
        return url('/app/'.$this->site->workspace->slug.'/sites/'.$this->site->getKey());
    }
}
