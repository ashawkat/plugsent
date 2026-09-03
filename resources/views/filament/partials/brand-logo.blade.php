{{-- Plugsent brand lockup: one SVG so it can never wrap or overflow.
     The wordmark uses currentColor and Google Sans (loaded via plugsent.css). --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 236 64" class="h-9" role="img" aria-label="Plugsent">
    <defs>
        <linearGradient id="plugsent-mark-bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#818CF8"/>
            <stop offset="1" stop-color="#4338CA"/>
        </linearGradient>
    </defs>
    <rect width="64" height="64" rx="14.5" fill="url(#plugsent-mark-bg)"/>
    <g fill="#FFFFFF">
        <rect x="24.5" y="11" width="6.5" height="14" rx="3.25"/>
        <rect x="33" y="11" width="6.5" height="14" rx="3.25"/>
        <path d="M19 23.5 h26 v13.5 a13 13 0 0 1 -13 13 a13 13 0 0 1 -13 -13 Z"/>
    </g>
    <path d="M32 50 v3.5 c0 3 -2.2 4.5 -4.8 4.5 h-4.4 c-2.2 0 -3.8 1.3 -3.8 3"
          fill="none" stroke="#FFFFFF" stroke-width="4.5" stroke-linecap="round"/>
    <text x="78" y="45" fill="currentColor"
          font-family="'Google Sans', system-ui, sans-serif"
          font-size="38" font-weight="600" letter-spacing="-1">Plugsent</text>
</svg>
