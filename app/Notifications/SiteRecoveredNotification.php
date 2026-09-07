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
            ->view('mail.plugsent', [
                'bannerColor' => '#16a34a',
                'bannerLabel' => 'Recovered',
                'title' => $this->site->name.' is responding again',
                'intro' => [
                    '<strong>'.$this->site->url.'</strong> answered the uptime check again after being down.',
                ],
                'rows' => [
                    ['label' => 'Site', 'value' => $this->site->name],
                    ['label' => 'Went down', 'value' => $this->incident->started_at->format('M j, H:i')],
                    ['label' => 'Recovered', 'value' => $this->incident->ended_at?->format('M j, H:i') ?? '—'],
                    ['label' => 'Downtime', 'value' => $downFor.' ('.$this->incident->failure_count.' failed checks)'],
                ],
                'buttonUrl' => url('/app/'.$this->site->workspace->slug.'/sites/'.$this->site->getKey().'?tab=uptime'),
                'buttonText' => 'Open uptime details',
                'footNote' => 'You receive this because you are a workspace admin and the site has uptime monitoring enabled.',
            ]);
    }
}
