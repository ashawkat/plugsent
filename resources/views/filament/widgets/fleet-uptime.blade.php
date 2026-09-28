<div class="plugsent-dash-card plugsent-dash-card-flush">
    <div class="plugsent-dash-card-head">
        <h3 class="plugsent-dash-card-title">Uptime · last 30 days</h3>
        <span class="plugsent-up-legend">
            <span><i class="plugsent-up-key plugsent-up-ok"></i>Operational</span>
            <span><i class="plugsent-up-key plugsent-up-warn"></i>Degraded</span>
            <span><i class="plugsent-up-key plugsent-up-bad"></i>Outage</span>
        </span>
    </div>

    @if (count($rows) === 0)
        <div class="plugsent-empty">No sites have uptime monitoring enabled yet.</div>
    @else
        <ul class="plugsent-up-rows">
            @foreach ($rows as $row)
                <li class="plugsent-up-row">
                    <a href="{{ $row['url'] }}" class="plugsent-up-site">{{ $row['name'] }}</a>
                    <span class="plugsent-up-bars">
                        @foreach ($row['days'] as $index => $day)
                            <i class="plugsent-up-bar plugsent-up-bar-{{ $day }}" title="{{ $row['dayLabels'][$index] }}"></i>
                        @endforeach
                    </span>
                    <span class="plugsent-up-pct plugsent-up-pct-{{ $row['pct'] >= 99.5 ? 'ok' : ($row['pct'] >= 95 ? 'warn' : 'bad') }}">{{ number_format($row['pct'], 2) }}%</span>
                </li>
            @endforeach
        </ul>
        @if ($hidden > 0)
            <div class="plugsent-dash-card-foot">
                <a href="{{ $sitesUrl }}">Worst {{ count($rows) }} of {{ $monitored }} monitored sites — open Sites →</a>
            </div>
        @endif
    @endif
</div>
