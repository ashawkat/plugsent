<div class="plugsent-dash-card">
    <h3 class="plugsent-dash-card-title">Recent activity</h3>

    @if (count($items) === 0)
        <div class="plugsent-empty">No site activity yet — updates, scans, and hardening changes will show up here.</div>
    @else
        <ul class="plugsent-feed">
            @foreach ($items as $item)
                <li class="plugsent-feed-item">
                    <span class="plugsent-feed-dot plugsent-feed-dot-{{ $item['tone'] }}"></span>
                    <div class="plugsent-feed-body">
                        <p class="plugsent-feed-text">
                            {{ $item['subject'] }}
                            — @if ($item['url'] !== null)<a href="{{ $item['url'] }}">{{ $item['site'] }}</a>@else{{ $item['site'] }}@endif
                        </p>
                        <p class="plugsent-feed-time">{{ $item['when'] }} · {{ $item['status'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
