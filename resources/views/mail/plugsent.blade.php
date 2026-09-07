{{-- Plugsent branded email layout.
     Expected variables (all optional except $title):
     $bannerColor  hex accent for the top banner (default indigo)
     $bannerLabel  short text in the banner (e.g. "Site down")
     $title        heading
     $intro        array of paragraph lines (may contain simple <strong>)
     $rows         array of ['label' => .., 'value' => ..] fact rows
     $buttonUrl / $buttonText  call-to-action
     $footNote     small print under the button
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Plugsent' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
                <tr>
                    <td style="padding:18px 28px; border-bottom:1px solid #eef2f7;">
                        <span style="font-size:18px; font-weight:800; letter-spacing:-0.02em; color:#4f46e5;">⚡ Plugsent</span>
                    </td>
                </tr>
                <tr>
                    <td style="background:{{ $bannerColor ?? '#4f46e5' }}; padding:14px 28px;">
                        <span style="color:#ffffff; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">
                            {{ $bannerLabel ?? 'Notification' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 14px; font-size:20px; line-height:1.35; color:#0f172a;">{{ $title }}</h1>

                        @foreach((array) ($intro ?? []) as $line)
                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#334155;">{!! $line !!}</p>
                        @endforeach

                        @if(! empty($rows))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0; border:1px solid #e2e8f0; border-radius:8px;">
                                @foreach((array) $rows as $row)
                                    <tr>
                                        <td style="padding:9px 14px; font-size:13px; color:#64748b; width:38%; border-bottom:1px solid #f1f5f9;">{{ $row['label'] }}</td>
                                        <td style="padding:9px 14px; font-size:13px; color:#0f172a; font-weight:600; border-bottom:1px solid #f1f5f9;">{!! $row['value'] !!}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if(! empty($buttonUrl))
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 6px;">
                                <tr>
                                    <td style="background:#4f46e5; border-radius:8px;">
                                        <a href="{{ $buttonUrl }}" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">{{ $buttonText ?? 'Open dashboard' }}</a>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if(! empty($footNote))
                            <p style="margin:14px 0 0; font-size:12px; line-height:1.6; color:#94a3b8;">{{ $footNote }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px; border-top:1px solid #eef2f7; font-size:11px; color:#94a3b8;">
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
