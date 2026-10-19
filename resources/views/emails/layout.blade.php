@php $so = ($locale ?? 'en') === 'so'; @endphp
<!doctype html>
<html lang="{{ $so ? 'so' : 'en' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $title ?? 'Doogsan Media' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f2ec;font-family:Helvetica,Arial,sans-serif;color:#0f1a16;">
@isset($preheader)<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>@endisset
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ec;">
<tr><td align="center" style="padding:24px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;">
        <tr><td style="background:#0b1f17;padding:22px 28px;border-bottom:4px solid #a5ea4f;">
            <a href="{{ url($so ? '/so' : '/en') }}" style="text-decoration:none;"><img src="{{ brand_logo('dark', $locale ?? 'en') }}" alt="Doogsan Media" height="40" style="display:block;height:40px;width:auto;border:0;"></a>
        </td></tr>
        <tr><td style="padding:28px;">
            @yield('body')
        </td></tr>
        <tr><td style="background:#f0ede4;padding:20px 28px;font-size:12px;line-height:1.6;color:#55625c;">
            @yield('footer')
            <p style="margin:8px 0 0;">Doogsan Media · <a href="mailto:{{ setting('contact_email', 'info@doogsanmedia.com') }}" style="color:#3f7f12;">{{ setting('contact_email', 'info@doogsanmedia.com') }}</a> · <a href="{{ url($so ? '/so' : '/en') }}" style="color:#3f7f12;">doogsanmedia.com</a></p>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
