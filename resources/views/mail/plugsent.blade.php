{{-- Plugsent branded email layout.
     Expected variables (all optional except $title):
     $bannerColor  hex accent for the top banner (default indigo)
     $bannerLabel  short text in the banner (e.g. "Site down")
     $title        heading
     $intro        array of paragraph lines (may contain simple <strong>)
     $rows         array of ['label' => .., 'value' => ..] fact rows (may contain HTML)
     $buttonUrl / $buttonText  call-to-action
     $footNote     small print under the button

     Google Sans is self-hosted by the app; clients that strip @font-face
     (Gmail web) fall back to the system stack. --}}
@php
    $fontStack = "'Google Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Plugsent' }}</title>
    <style>
        @font-face {
            font-family: 'Google Sans';
            font-style: normal;
            font-weight: 400 700;
            font-display: swap;
            src: url('{{ asset('fonts/google-sans-latin-ext.woff2') }}') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
        }
        @font-face {
            font-family: 'Google Sans';
            font-style: normal;
            font-weight: 400 700;
            font-display: swap;
            src: url('{{ asset('fonts/google-sans-latin.woff2') }}') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:{{ $fontStack }}; color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
                <tr>
                    <td style="padding:18px 28px; border-bottom:1px solid #eef2f7; font-family:{{ $fontStack }};">
                        <span style="font-size:18px; font-weight:800; letter-spacing:-0.02em; color:#4f46e5;">⚡ Plugsent</span>
                    </td>
                </tr>
                <tr>
                    <td style="background:{{ $bannerColor ?? '#4f46e5' }}; padding:14px 28px; font-family:{{ $fontStack }};">
                        <span style="color:#ffffff; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">
                            {{ $bannerLabel ?? 'Notification' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px; font-family:{{ $fontStack }};">
                        <h1 style="margin:0 0 14px; font-size:20px; line-height:1.35; color:#0f172a; font-family:{{ $fontStack }};">{{ $title }}</h1>

                        @foreach((array) ($intro ?? []) as $line)
                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#334155; font-family:{{ $fontStack }};">{!! $line !!}</p>
                        @endforeach

                        @if(! empty($rows))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0; border:1px solid #e2e8f0; border-radius:8px;">
                                @foreach((array) $rows as $row)
                                    <tr>
                                        <td style="padding:9px 14px; font-size:13px; color:#64748b; width:38%; border-bottom:1px solid #f1f5f9; font-family:{{ $fontStack }};">{!! $row['label'] !!}</td>
                                        <td style="padding:9px 14px; font-size:13px; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9; font-family:{{ $fontStack }};">{!! $row['value'] !!}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if(! empty($buttonUrl))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 6px;">
                                <tr>
                                    <td style="background:#4f46e5; border-radius:8px; font-family:{{ $fontStack }};">
                                        <a href="{{ $buttonUrl }}" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; font-family:{{ $fontStack }};">{{ $buttonText ?? 'Open dashboard' }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if(! empty($footNote))
                            <p style="margin:14px 0 0; font-size:12px; line-height:1.6; color:#94a3b8; font-family:{{ $fontStack }};">{{ $footNote }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px; border-top:1px solid #eef2f7; font-size:11px; color:#94a3b8; font-family:{{ $fontStack }};">
                        Sent by Plugsent — your self-hosted WordPress fleet manager.<br>
                        Manage email preferences in your Plugsent profile.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
