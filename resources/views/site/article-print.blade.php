<!DOCTYPE html>
<html lang="{{ $t->locale }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ $t->title }} — Doogsan Media</title>
    <style>
        body { font-family: Georgia, "Times New Roman", serif; max-width: 42rem; margin: 2rem auto; padding: 0 1rem; color: #111; line-height: 1.6; font-size: 12pt; }
        header img { height: 36px } h1 { font-size: 24pt; line-height: 1.15; margin: 1rem 0 .5rem } .dek { font-size: 14pt; color: #333 }
        .meta { font: 9pt/1.4 system-ui, sans-serif; color: #555; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: .5rem 0; margin: 1rem 0 }
        figure { margin: 1rem 0 } img { max-width: 100% } figcaption { font: 9pt system-ui, sans-serif; color: #555 }
        .pull-quote blockquote { font-size: 15pt; border-left: 3px solid #04322a; padding-left: 1rem; margin: 1rem 0 }
        footer { font: 9pt system-ui, sans-serif; color: #555; margin-top: 2rem; border-top: 1px solid #ccc; padding-top: .5rem }
        @media print { .noprint { display: none } }
    </style>
</head>
<body onload="window.print()">
    <header><img src="{{ asset('images/brand/doogsan-logo-light.png') }}" alt="Doogsan Media"></header>
    <h1>{{ $t->title }}</h1>
    @if ($t->standfirst)<p class="dek">{{ $t->standfirst }}</p>@endif
    <p class="meta">{{ $a->author?->name }} · {{ news_date($t->published_at) }}@if ($t->wasUpdated()) · {{ __('Updated') }} {{ news_date($t->content_updated_at) }}@endif</p>
    @if ($a->featuredMedia)<figure><img src="{{ $a->featuredMedia->url(1600) }}" alt="{{ $a->featuredMedia->altFor() }}"><figcaption>{{ $a->featuredMedia->captionFor() }} {{ $a->featuredMedia->credit }}</figcaption></figure>@endif
    {!! $t->body_html !!}
    <footer>{{ $t->url() }} · © Doogsan Media</footer>
    <p class="noprint"><a href="{{ $t->url() }}">← {{ __('Back to the story') }}</a></p>
</body>
</html>
