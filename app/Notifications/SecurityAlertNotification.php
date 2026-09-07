<?php

namespace App\Notifications;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public Site $site, public $items, public string $siteUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function envelope(object $notifiable): Envelope
    {
        $total = (int) $this->items->sum('vuln_count');

        return new Envelope(
            subject: '🛡 '.$this->site->name.': '.$total.' vulnerabilities detected',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = (int) $this->items->sum('vuln_count');
        $fixable = $this->items->where('update_available', true)->count();

        $rows = $this->items->take(8)
            ->map(fn ($item) => [
                'label' => $item->name.' ('.$item->version.')',
                'value' => '⚠ '.$item->vuln_count.($item->update_available ? ' — fix available: '.$item->update_version : ''),
            ])
            ->all();

        if ($this->items->count() > 8) {
            $rows[] = ['label' => '…', 'value' => ($this->items->count() - 8).' more item(s) — open the dashboard for the full list'];
        }

        return (new MailMessage)
            ->subject('🛡 '.$this->site->name.': '.$total.' vulnerabilities detected')
            ->view('mail.plugsent', [
                'bannerColor' => '#d97706',
                'bannerLabel' => 'Security alert',
                'title' => $total.' known vulnerabilit'.($total === 1 ? 'y' : 'ies').' on '.$this->site->name,
                'intro' => [
                    'The vulnerability feed matched software installed on <strong>'.$this->site->url.'</strong>.',
                    $fixable > 0
                        ? '<strong>'.$fixable.' item(s)</strong> have a patched release available — updating fixes them.'
                        : 'No patched release is available yet for some items — consider deactivating them until vendors ship fixes.',
                ],
                'rows' => $rows,
                'buttonUrl' => $this->siteUrl,
                'buttonText' => 'Open security details',
                'footNote' => 'Sent at most once a day per site. Manage these emails from your Plugsent profile.',
            ]);
    }
}
