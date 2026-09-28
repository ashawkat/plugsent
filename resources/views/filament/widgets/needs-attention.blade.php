<div class="plugsent-dash-card plugsent-dash-card-flush">
    <div class="plugsent-dash-card-head">
        <h3 class="plugsent-dash-card-title">Needs attention</h3>
        @if (count($rows) > 0)
            <span class="plugsent-badge plugsent-badge-danger">{{ count($rows) }}</span>
        @endif
    </div>

    @if (count($rows) === 0)
        <div class="plugsent-empty">Every site is connected, up to date, and passing its checks. Enjoy the quiet.</div>
    @else
        <div class="plugsent-table-wrap">
            <table class="plugsent-att-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Score</th>
                        <th>Updates</th>
                        <th>Vulns</th>
                        <th>Uptime</th>
                        <th>Why</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ $row['url'] }}" class="plugsent-att-site">{{ $row['site']->name }}</a>
                                <span class="plugsent-att-url">{{ $row['site']->url }}</span>
                            </td>
                            <td>
                                @if ($row['score'] !== null)
                                    <span class="plugsent-pill plugsent-pill-{{ $row['score'] >= 80 ? 'ok' : ($row['score'] >= 50 ? 'warn' : 'bad') }}">{{ $row['score'] }}</span>
                                @else
                                    <span class="plugsent-pill plugsent-pill-none">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['updates'] > 0)
                                    <span class="plugsent-pill plugsent-pill-warn">{{ $row['updates'] }}</span>
                                @else
                                    <span class="plugsent-pill plugsent-pill-none">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['vulns'] > 0)
                                    <span class="plugsent-pill plugsent-pill-bad">{{ $row['vulns'] }}</span>
                                @else
                                    <span class="plugsent-pill plugsent-pill-none">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['uptime'] !== null)
                                    <span class="plugsent-pill plugsent-pill-{{ $row['uptime'] >= 99.5 ? 'ok' : ($row['uptime'] >= 95 ? 'warn' : 'bad') }}">{{ number_format($row['uptime'], 2) }}%</span>
                                @else
                                    <span class="plugsent-pill plugsent-pill-none">—</span>
                                @endif
                            </td>
                            <td class="plugsent-att-why">{{ implode(' · ', $row['reasons']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="plugsent-dash-card-foot">
            <a href="{{ $sitesUrl }}">Open Sites, sorted by risk →</a>
        </div>
    @endif
</div>
