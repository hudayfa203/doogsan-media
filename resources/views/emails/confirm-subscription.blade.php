@extends('emails.layout', ['title' => $locale === 'so' ? 'Xaqiiji isdiiwaangelintaada' : 'Confirm your subscription', 'preheader' => $locale === 'so' ? 'Hal tallaabo ayaa kaa harsan.' : 'One step left.'])

@section('body')
@if ($locale === 'so')
    <h1 style="margin:0 0 12px;font-family:Georgia,serif;font-size:26px;line-height:1.25;">Xaqiiji isdiiwaangelintaada</h1>
    <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">Waad ku mahadsan tahay inaad isku diiwaangelisay warsidaha Doogsan Media. Riix badhanka hoose si aad u xaqiijiso cinwaankaaga iimaylka.</p>
    <p style="margin:0 0 24px;"><a href="{{ $url }}" style="display:inline-block;background:#a5ea4f;color:#0b1f17;font-weight:bold;font-size:15px;padding:13px 24px;border-radius:6px;text-decoration:none;">Xaqiiji isdiiwaangelinta</a></p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#55625c;">Haddii badhanku shaqayn waayo, koobi ka samee xiriirkan: <br><a href="{{ $url }}" style="color:#3f7f12;word-break:break-all;">{{ $url }}</a></p>
@else
    <h1 style="margin:0 0 12px;font-family:Georgia,serif;font-size:26px;line-height:1.25;">Confirm your subscription</h1>
    <p style="margin:0 0 20px;font-size:16px;line-height:1.6;">Thanks for signing up to the Doogsan Media newsletter. Please confirm your email address to start receiving it.</p>
    <p style="margin:0 0 24px;"><a href="{{ $url }}" style="display:inline-block;background:#a5ea4f;color:#0b1f17;font-weight:bold;font-size:15px;padding:13px 24px;border-radius:6px;text-decoration:none;">Confirm subscription</a></p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#55625c;">If the button doesn't work, copy this link into your browser:<br><a href="{{ $url }}" style="color:#3f7f12;word-break:break-all;">{{ $url }}</a></p>
@endif
@endsection

@section('footer')
<p style="margin:0;">{{ $locale === 'so' ? 'Haddii aadan adigu codsan, iska indho tir iimaylkan — lagugu ma darayo liiska.' : "If you didn't request this, ignore this email — you won't be added to the list." }}</p>
@endsection
