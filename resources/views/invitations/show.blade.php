<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Workspace invitation — Plugsent</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
        .field { margin-bottom:14px; }
        .field label { display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; margin-bottom:6px; }
        .input { width:100%; box-sizing:border-box; padding:10px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; color:#111827; background:#fff; }
        .input:disabled { background:#f9fafb; color:#6b7280; }
        .input:focus { outline:2px solid #c7d2fe; border-color:#4f46e5; }
        .error { color:#dc2626; font-size:13px; margin:-6px 0 12px; }
        .pw-wrap { position: relative; }
        .pw-wrap .input { width: 100%; padding-right: 2.6rem; }
        .pw-eye { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
            border: none; background: transparent; color: #64748b; cursor: pointer; padding: 0.25rem;
            display: inline-flex; border-radius: 0.375rem; }
        .pw-eye:hover { color: #0f172a; }
        .pw-eye svg { width: 18px; height: 18px; }
        [x-cloak] { display: none !important; }
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
            @elseif($emailTaken)
                <p class="muted">An account already exists for <strong>{{ $invitation->email }}</strong>. Log in with it to accept the invitation.</p>
                <a class="btn" href="{{ url('/app/login') }}">Log in to accept</a>
            @else
                <p class="muted" style="margin-bottom:18px">Your email and workspace are already set — just pick a name and password.</p>

                <form method="post" action="{{ route('invitations.register', ['token' => $invitation->token]) }}">
                    @csrf

                    <div class="field">
                        <label>Email</label>
                        <input type="email" class="input" value="{{ $invitation->email }}" disabled>
                    </div>

                    <div class="field">
                        <label>Your name</label>
                        <input type="text" name="name" class="input" value="{{ old('name') }}" required maxlength="255" autofocus>
                    </div>
                    @error('name')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <div class="field">
                        <label>Password</label>
                        <div class="pw-wrap" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="password" class="input" required>
                        <button type="button" class="pw-eye" @click="show = !show" aria-label="Toggle password visibility">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
                    </div>

                    <div class="field">
                        <label>Confirm password</label>
                        <div class="pw-wrap" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="password_confirmation" class="input" required>
                        <button type="button" class="pw-eye" @click="show = !show" aria-label="Toggle password visibility">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                        </button>
                    </div>
                    </div>
                    @error('password')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="btn">Create account &amp; join {{ $invitation->workspace->name }}</button>
                </form>
            @endif
        @endif
    </div>
</body>
</html>
