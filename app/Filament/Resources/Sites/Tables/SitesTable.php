<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Actions\EnqueueSiteCommand;
use App\Actions\GetFleetSummary;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Site $record): string => SiteResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('url')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('project.name')
                    ->label('Project'),
                TextColumn::make('pending_updates')
                    ->label('Updates')
                    ->state(function (Site $record): string {
                        $pending = $record->inventory()
                            ->where('update_available', true)
                            ->pluck('context');

                        if ($pending->isEmpty()) {
                            return 'Up to date';
                        }

                        $parts = [];

                        foreach (['core' => 'core', 'plugin' => 'plugins', 'theme' => 'themes'] as $context => $label) {
                            $count = $pending->filter(fn (string $item): bool => $item === $context)->count();

                            if ($count > 0) {
                                $parts[] = $count.' '.$label;
                            }
                        }

                        return implode(' · ', $parts);
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Up to date' ? 'success' : 'warning'),
                TextColumn::make('vulnerabilities')
                    ->label('Vulnerabilities')
                    ->html()
                    ->state(fn (Site $record): string => self::severityDots($record))
                    ->color('gray'),
                TextColumn::make('security_score')
                    ->label('Security')
                    ->badge()
                    ->state(fn (Site $record): string => $record->security_score !== null
                        ? $record->security_score.'/100'
                        : '—')
                    ->color(fn (Site $record): string => match (true) {
                        $record->security_score === null => 'gray',
                        $record->security_score >= 80 => 'success',
                        $record->security_score >= 50 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('uptime_status')
                    ->label('Uptime')
                    ->html()
                    ->state(fn (Site $record): string => self::uptimeStrip($record)),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Site $record): string => $record->isStale() ? 'unreachable' : $record->status)
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'unreachable' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Site $record): ?string => $record->isStale()
                        ? 'No check-in from this site for '.($record->last_seen_at?->diffForHumans() ?? 'a long time').' — it may be offline or its connector is stalled.'
                        : null),
                TextColumn::make('last_seen_at')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'connected' => 'Connected',
                        'error' => 'Error',
                    ]),
                SelectFilter::make('project')
                    ->relationship(
                        'project',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where(
                            'workspace_id',
                            Filament::getTenant()?->getKey(),
                        ),
                    ),
            ])
            ->groups([
                Group::make('project.name')->label('Project')->collapsible(),
            ])
            ->defaultGroup('project.name')
            ->defaultSort(fn (Builder $query): Builder => $query
                // Risk ordering: disconnected sites first, then lowest score
                // (nulls last), then name. User-triggered sorts replace it.
                ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', ['connected'])
                ->orderByRaw('security_score IS NULL ASC')
                ->orderBy('security_score')
                ->orderBy('name'))
            ->recordClasses(fn (Site $record): string => $record->isConnected() ? '' : 'plugsent-risk-row')
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-pencil-square'),
                Action::make('refreshInventory')
                    ->label('Refresh inventory')
                    ->iconButton()
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Site $record): bool => $record->isConnected())
                    ->action(function (Site $record): void {
                        app(EnqueueSiteCommand::class)($record, 'inventory.get');

                        Notification::make()
                            ->title('Inventory refresh queued')
                            ->body("{$record->name} will check in within a minute.")
                            ->success()
                            ->send();
                    }),
                Action::make('revoke')
                    ->label('Revoke access')
                    ->iconButton()
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Site $record): bool => $record->isConnected())
                    ->action(function (Site $record): void {
                        $record->credential()->update(['status' => 'revoked']);
                        $record->forceFill(['status' => 'pending'])->save();

                        Notification::make()
                            ->title('Connector access revoked')
                            ->body("{$record->name} can no longer check in until it pairs again.")
                            ->warning()
                            ->send();
                    }),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Severity dots ("● 1 crit · ● 2 high · …") from the cached fleet
     * summary, so the whole table shares one vulnerability-matching pass.
     */
    private static function severityDots(Site $record): string
    {
        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());
        $perSite = $summary['per_site'][$record->getKey()] ?? null;

        if ($perSite === null || $perSite['vulns'] === 0) {
            return '<span class="plugsent-pill plugsent-pill-none">None known</span>';
        }

        $labels = [
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            'unknown' => 'unrated',
        ];

        $dots = [];

        foreach ($labels as $severity => $label) {
            $count = $perSite['vuln_severities'][$severity];

            if ($count > 0) {
                $dots[] = '<span class="plugsent-sev plugsent-sev-'.$severity.'" title="'.$count.' '.$label.'">'
                    .'<i></i>'.$count.' '.($count === 1 ? $label : Str::plural($label)).'</span>';
            }
        }

        return implode('', $dots);
    }

    /**
     * 30-day mini strip + rate from the cached fleet summary's uptime rows.
     */
    private static function uptimeStrip(Site $record): string
    {
        if (! $record->uptime_enabled) {
            return '<span class="plugsent-pill plugsent-pill-none">Paused</span>';
        }

        $summary = app(GetFleetSummary::class)(Filament::getTenant(), auth()->user());

        $row = collect($summary['uptime_rows'])
            ->first(fn (array $row): bool => $row['site_id'] === $record->getKey());

        if ($row === null) {
            return '<span class="plugsent-pill plugsent-pill-none">—</span>';
        }

        $tone = $row['pct'] >= 99.5 ? 'ok' : ($row['pct'] >= 95 ? 'warn' : 'bad');

        $bars = '';

        foreach ($row['days'] as $index => $day) {
            if ($day['downtime_seconds'] === 0) {
                $barTone = 'ok';
            } else {
                $dayPct = (1 - $day['downtime_seconds'] / 86400) * 100;
                $barTone = $dayPct >= 95 ? 'warn' : 'bad';
            }

            $bars .= '<i class="plugsent-up-bar plugsent-up-bar-'.$barTone.'" title="'.e($day['label']).'"></i>';
        }

        return '<span class="plugsent-tstrip">'.$bars.'</span>'
            .'<span class="plugsent-up-pct plugsent-up-pct-'.$tone.'">'.number_format($row['pct'], 2).'%</span>';
    }
}
