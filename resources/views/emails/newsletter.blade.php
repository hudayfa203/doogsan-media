@extends('emails.layout', ['title' => $campaign->subject, 'preheader' => \Illuminate\Support\Str::limit((string) $campaign->intro, 120)])

@section('body')
<p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;color:#3f7f12;">{{ $locale === 'so' ? 'Warsidaha Doogsan' : 'Doogsan Briefing' }} · {{ ($d = local_time(now()))->day.' '.trans('month.'.$d->month, [], $locale).' '.$d->year }}</p>
<h1 style="margin:0 0 14px;font-family:Georgia,serif;font-size:26px;line-height:1.25;">{{ $campaign->subject }}</h1>
@if ($campaign->intro)
    <p style="margin:0 0 22px;font-size:16px;line-height:1.6;white-space:pre-line;">{{ $campaign->intro }}</p>
@endif

@foreach ($stories as $i => $t)
    @php $img = $t->article?->featuredMedia; @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e4e0d4;">
        <tr><td style="padding:18px 0;">
            @if ($img && $i === 0)
                <a href="{{ $t->url() }}"><img src="{{ $img->url(960) }}" alt="{{ $img->altFor($locale) }}" width="544" style="display:block;width:100%;height:auto;border-radius:4px;margin-bottom:12px;border:0;"></a>
            @endif
            <a href="{{ $t->url() }}" style="font-family:Georgia,serif;font-size:{{ $i === 0 ? '22px' : '18px' }};line-height:1.3;font-weight:bold;color:#0f1a16;text-decoration:none;">{{ $t->title }}</a>
            @if ($t->standfirst)<p style="margin:6px 0 0;font-size:14px;line-height:1.55;color:#3a4943;">{{ \Illuminate\Support\Str::limit($t->standfirst, 220) }}</p>@endif
            <p style="margin:8px 0 0;"><a href="{{ $t->url() }}" style="font-size:13px;font-weight:bold;color:#3f7f12;text-decoration:none;">{{ $locale === 'so' ? 'Akhri sheekada →' : 'Read the story →' }}</a></p>
        </td></tr>
    </table>
@endforeach
@endsection

@section('footer')
<p style="margin:0;">{{ $locale === 'so' ? 'Waxaad heshay iimaylkan sababtoo ah waxaad isku diiwaangelisay warsidaha Doogsan Media.' : "You're receiving this because you subscribed to the Doogsan Media newsletter." }}
<a href="{{ $unsubscribe }}" style="color:#3f7f12;">{{ $locale === 'so' ? 'Ka bax liiska' : 'Unsubscribe' }}</a></p>
@endsection
