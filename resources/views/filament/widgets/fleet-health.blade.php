<div class="plugsent-dash-card">
    <h3 class="plugsent-dash-card-title">Fleet health</h3>

    @if ($score === null)
        <div class="plugsent-empty">No security scans yet — scores appear after connected sites report their first scan.</div>
    @else
        <div class="plugsent-ring-wrap">
            <div class="plugsent-ring plugsent-ring-{{ $tone }}">
                <svg viewBox="0 0 150 150" aria-hidden="true">
                    <circle cx="75" cy="75" r="62" class="plugsent-ring-track"/>
                    <circle cx="75" cy="75" r="62" class="plugsent-ring-fill" stroke-dasharray="{{ $dasharray }} 389.6"/>
                </svg>
                <div class="plugsent-ring-center">
                    <span class="plugsent-ring-value">{{ $score }}</span>
                    <span class="plugsent-ring-of">/ 100 avg score</span>
                </div>
            </div>
            <p class="plugsent-ring-caption">
                average across {{ $summary['sites_scored'] }} scored {{ Str::plural('site', $summary['sites_scored']) }}
            </p>
            @if ($delta !== null && $delta !== 0)
                <span class="plugsent-delta plugsent-delta-{{ $delta > 0 ? 'up' : 'down' }}">{{ $delta > 0 ? '▲' : '▼' }} {{ abs($delta) }} vs last week</span>
            @endif
        </div>

        <ul class="plugsent-dash-stat-list">
            <li>
                <span class="plugsent-dot plugsent-dot-good"></span>
                <span><strong>{{ $summary['sites_scoring_80'] }} of {{ $summary['sites_scored'] }}</strong> sites scoring 80+</span>
            </li>
            <li>
                <span class="plugsent-dot plugsent-dot-fair"></span>
                <span><strong>{{ $summary['checks_avg_passing'] ?? '—' }} of 14</strong> checks passing on average</span>
            </li>
            @if ($summary['weakest'] !== null)
                <li>
                    <span class="plugsent-dot plugsent-dot-bad"></span>
                    <span>Weakest: <strong>{{ $summary['weakest']->name }}</strong> — {{ $summary['weakest']->security_score }}</span>
                </li>
            @endif
        </ul>
    @endif
</div>
