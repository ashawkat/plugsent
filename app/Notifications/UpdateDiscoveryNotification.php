<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Timely counterpart to the daily digest: sent as soon as a fresh
 * inventory snapshot shows a changed set of pending updates on one site.
 */
class UpdateDiscoveryNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, InventoryItem>  $items
     */
    public function __construct(public Workspace $workspace, public Site $site, public Collection $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function envelope(object $notifiable): Envelope
    {
        return new Envelope(
            subject: '🆕 '.$this->items->count().' update'.($this->items->count() === 1 ? '' : 's').' available on '.$this->site->name,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteUrl = url('/app/'.$this->workspace->slug.'/sites/'.$this->site->getKey().'?tab=plugins');

        $list = $this->items
            ->map(fn (InventoryItem $item) => $item->name.' ('.$item->version.' → '.$item->update_version.')')
            ->implode('<br>');

        return (new MailMessage)
            ->view('mail.plugsent', [
                'bannerColor' => '#4f46e5',
                'bannerLabel' => 'Updates detected',
                'title' => $this->items->count().' update'.($this->items->count() === 1 ? '' : 's').' available on '.$this->site->name,
                'intro' => [
                    'A fresh inventory scan of '.$this->site->url.' found software with updates available.',
                    'Updates run from the dashboard — safe updates include a restore point and automatic rollback.',
                ],
                'rows' => [[
                    'label' => '<a href="'.$siteUrl.'" style="color:#4f46e5; text-decoration:none;">'.$this->site->name.' ↗</a>',
                    'value' => $list,
                ]],
                'buttonUrl' => $siteUrl,
                'buttonText' => 'Review updates',
                'footNote' => 'This email is sent when a scan finds a new set of updates. Manage these emails from your Plugsent profile.',
            ]);
    }
}
