<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Workspace invitation — Plugsent</title>
    <style>
        body { margin:0; padding:60px 20px; background:#f3f4f6; font-family:ui-sans-serif,system-ui,-apple-system,'Google Sans',sans-serif; display:flex; justify-content:center; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:36px 40px; max-width:520px; width:100%; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        .brand { display:flex; align-items:center; gap:10px; margin-bottom:26px; }
        .brand-mark { width:34px; height:34px; border-radius:8px; background:linear-gradient(135deg,#818CF8,#4338CA); }
        .brand-name { font-weight:700; font-size:18px; color:#111827; }
        h1 { font-size:20px; margin:0 0 8px; color:#111827; }
        p { font-size:14px; color:#4b5563; line-height:1.6; margin:0 0 18px; }
        .pill { display:inline-block; padding:2px 10px; border-radius:999px; background:#e0e7ff; color:#4338ca; font-size:12px; font-weight:600; }
        .btn { display:inline-block; padding:10px 22px; border-radius:8px; background:#4f46e5; color:#fff; text-decoration:none; font-weight:600; font-size:14px; border:none; cursor:pointer; }
        .btn:hover { background:#4338ca; }
        .btn-secondary { background:#fff; color:#374151; border:1px solid #d1d5db; margin-right:10px; }
        .muted { color:#6b7280; font-size:13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <div class="brand-mark"></div>
            <div class="brand-name">Plugsent</div>
        </div>

        @if(! $invitation)
            <h1>Invitation not available</h1>
            <p>This invitation link is invalid, already used, or expired. Ask the workspace owner to send a new one.</p>
        @else
            <h1>Join {{ $invitation->workspace->name }}</h1>
            <p>
                You've been invited to join <strong>{{ $invitation->workspace->name }}</strong> on Plugsent
                as <span class="pill">{{ $invitation->role }}</span>.
            </p>

            @if(auth()->check() && $authMatches)
                <form method="post" action="{{ route('invitations.accept', ['token' => $invitation->token]) }}">
                    @csrf
                    <button type="submit" class="btn">Accept invitation</button>
                </form>
            @elseif(auth()->check())
                <p class="muted">You're logged in as {{ auth()->user()->email }}, but this invitation was sent to <strong>{{ $invitation->email }}</strong>. Log in with that account to accept.</p>
                <a class="btn" href="{{ url('/app/login') }}">Log in</a>
            @else
                <a class="btn" href="{{ url('/app/login') }}">Log in to accept</a>
                <p class="muted" style="margin-top:12px">No account yet? <a href="{{ url('/app/register') }}">Register with {{ $invitation->email }}</a>, then reopen this link.</p>
            @endif
        @endif
    </div>
</body>
</html>
