<?php

namespace App\Filament\Widgets;

use App\Actions\GetFleetSummary;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\SiteCommand;
use App\Support\CommandSubject;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The latest commands across the visible sites — updates, scans,
 * hardening — as one fleet-wide feed.
 */
class SiteActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.site-activity';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $formatter = app(CommandSubject::class);

        $items = $summary['activity']
            ->map(function (SiteCommand $command) use ($formatter): array {
                $tone = match ($command->status) {
                    SiteCommand::STATUS_COMPLETED => 'ok',
                    SiteCommand::STATUS_FAILED => 'bad',
                    default => 'busy',
                };

                return [
                    'subject' => $formatter->format($command),
                    'site' => $command->site?->name ?? '—',
                    'url' => $command->site !== null
                        ? SiteResource::getUrl('view', ['record' => $command->site->getKey()])
                        : null,
                    'status' => $command->status,
                    'tone' => $tone,
                    'when' => $command->created_at?->diffForHumans() ?? '',
                ];
            })
            ->all();

        return ['items' => $items];
    }
}
