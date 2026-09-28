<div class="plugsent-dash-card">
    <div class="plugsent-dash-card-head">
        <h3 class="plugsent-dash-card-title">Security checks</h3>
    </div>

    @if ($sitesScanned === 0)
        <div class="plugsent-empty">No security scans yet — checks appear as connected sites report their first scan.</div>
    @else
        <p class="plugsent-dash-card-sub">across {{ $sitesScanned }} scanned {{ Str::plural('site', $sitesScanned) }} · worst first</p>
        <ul class="plugsent-check-list">
            @foreach ($checks as $check)
                <li class="plugsent-check-row">
                    <span class="plugsent-check-label" title="{{ $check['label'] }}">{{ $check['label'] }}</span>
                    <span class="plugsent-check-track">
                        <span class="plugsent-check-fill plugsent-check-fill-{{ $check['tone'] }}" style="width: {{ $check['percent'] }}%"></span>
                    </span>
                    <span class="plugsent-check-count">{{ $check['passed'] }}/{{ $check['total'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
