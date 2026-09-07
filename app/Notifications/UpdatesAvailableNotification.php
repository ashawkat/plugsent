<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The once-a-day "updates available" digest: every workspace site with
 * pending updates, listed with item names and target versions.
 */
class UpdatesAvailableNotification extends Notification
{
    use Queueable;

    /** @param array<int, array{site: mixed, items: Collection}> $siteDigest */
    public function __construct(public Workspace $workspace, public array $siteDigest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function envelope(object $notifiable): Envelope
    {
        $total = collect($this->siteDigest)->sum(fn ($entry) => $entry['items']->count());

        return new Envelope(
            subject: '📦 '.$total.' update'.($total === 1 ? '' : 's').' available across your sites',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = collect($this->siteDigest)->sum(fn ($entry) => $entry['items']->count());
        $siteCount = count($this->siteDigest);

        $rows = [];

        foreach ($this->siteDigest as $entry) {
            $site = $entry['site'];
            $siteUrl = url('/app/'.$this->workspace->slug.'/sites/'.$site->getKey().'?tab=plugins');

            $list = $entry['items']
                ->map(fn ($item) => $item->name.' ('.$item->version.' → '.$item->update_version.')')
                ->implode('<br>');

            $rows[] = [
                'label' => '<a href="'.$siteUrl.'" style="color:#4f46e5; text-decoration:none;">'.$site->name.' ↗</a>',
                'value' => $list,
            ];
        }

        return (new MailMessage)
            ->view('mail.plugsent', [
                'bannerColor' => '#4f46e5',
                'bannerLabel' => 'Daily updates digest',
                'title' => $total.' update'.($total === 1 ? '' : 's').' available on '.$siteCount.' site'.($siteCount === 1 ? '' : 's'),
                'intro' => [
                    'These plugins, themes, and core versions on your sites have updates available.',
                    'Updates run from the dashboard — safe updates include a restore point and automatic rollback.',
                ],
                'rows' => $rows,
                'buttonUrl' => url('/app/'.$this->workspace->slug.'/sites'),
                'buttonText' => 'Open your sites',
                'footNote' => 'This digest is sent once a day. Manage these emails from your Plugsent profile.',
            ]);
    }
}
