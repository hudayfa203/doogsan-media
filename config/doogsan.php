<?php

/*
|--------------------------------------------------------------------------
| Doogsan Media platform configuration
|--------------------------------------------------------------------------
| Business details that editors change day-to-day live in the database
| (Admin → Settings). This file holds structural configuration only.
*/

return [

    'default_edition' => env('DOOGSAN_DEFAULT_EDITION', 'en'),

    'timezone' => env('DOOGSAN_TIMEZONE', 'Africa/Mogadishu'),

    'contact_email' => env('DOOGSAN_CONTACT_EMAIL', 'info@doogsanmedia.com'),

    'enforce_2fa' => (bool) env('DOOGSAN_ENFORCE_2FA', false),

    'poll_interval' => (int) env('DOOGSAN_POLL_INTERVAL', 45),

    'editions' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'hreflang' => 'en',
            'og_locale' => 'en_GB',
            'html_lang' => 'en',
        ],
        'so' => [
            'name' => 'Somali',
            'native' => 'Soomaali',
            'hreflang' => 'so',
            'og_locale' => 'so_SO',
            'html_lang' => 'so',
        ],
    ],

    /*
    | Localized URL segments. Keys are internal route names; each edition
    | gets its own slug so /en/latest-news and /so/wararkii-ugu-dambeeyay
    | resolve to the same controller through real Laravel routes.
    */
    'segments' => [
        'latest' => ['en' => 'latest-news', 'so' => 'wararkii-ugu-dambeeyay'],
        'breaking' => ['en' => 'breaking-news', 'so' => 'war-deg-deg'],
        'article' => ['en' => 'news', 'so' => 'warar'],
        'section' => ['en' => 'section', 'so' => 'qaybta'],
        'topic' => ['en' => 'topic', 'so' => 'mowduuc'],
        'live' => ['en' => 'live', 'so' => 'toos'],
        'tv' => ['en' => 'live-tv', 'so' => 'tv-toos'],
        'videos' => ['en' => 'videos', 'so' => 'muuqaallo'],
        'documentaries' => ['en' => 'documentaries', 'so' => 'dokumentiyo'],
        'podcasts' => ['en' => 'podcasts', 'so' => 'podkaasyo'],
        'galleries' => ['en' => 'photos', 'so' => 'sawirro'],
        'media' => ['en' => 'media-centre', 'so' => 'xarunta-warbaahinta'],
        'authors' => ['en' => 'journalists', 'so' => 'weriyeyaasha'],
        'team' => ['en' => 'editorial-team', 'so' => 'tifaftirka'],
        'search' => ['en' => 'search', 'so' => 'raadi'],
        'archive' => ['en' => 'archive', 'so' => 'kaydka'],
        'contact' => ['en' => 'contact', 'so' => 'nala-soo-xiriir'],
        'tip' => ['en' => 'submit-a-tip', 'so' => 'soo-gudbi-xog'],
        'newsletter' => ['en' => 'newsletter', 'so' => 'warsidaha'],
        'page' => ['en' => 'about', 'so' => 'ku-saabsan'],
    ],

    /*
    | Article formats. Premium journalism listings (Analysis, Opinion, …)
    | are formats, so a story can sit in "Politics" and be "Analysis".
    */
    'formats' => [
        'news' => ['en' => 'News', 'so' => 'Warar', 'slug' => ['en' => 'news-reports', 'so' => 'warbixinno']],
        'analysis' => ['en' => 'Analysis', 'so' => 'Falanqayn', 'slug' => ['en' => 'analysis', 'so' => 'falanqayn']],
        'opinion' => ['en' => 'Opinion', 'so' => 'Aragti', 'slug' => ['en' => 'opinion', 'so' => 'aragti']],
        'investigation' => ['en' => 'Investigation', 'so' => 'Baaritaan', 'slug' => ['en' => 'investigations', 'so' => 'baaritaanno']],
        'in-depth' => ['en' => 'In-Depth', 'so' => 'Qoto-dheer', 'slug' => ['en' => 'in-depth', 'so' => 'qoto-dheer']],
        'interview' => ['en' => 'Interview', 'so' => 'Waraysi', 'slug' => ['en' => 'interviews', 'so' => 'waraysiyo']],
        'fact-check' => ['en' => 'Fact Check', 'so' => 'Xaqiijin', 'slug' => ['en' => 'fact-checks', 'so' => 'xaqiijin']],
        'special' => ['en' => 'Special Coverage', 'so' => 'Tebin Gaar ah', 'slug' => ['en' => 'special-coverage', 'so' => 'tebin-gaar-ah']],
        'editorial' => ['en' => 'Editorial', 'so' => 'Tifaftir', 'slug' => ['en' => 'editorials', 'so' => 'tifaftirro']],
        'gallery' => ['en' => 'Photo Essay', 'so' => 'Sheeko Sawir', 'slug' => ['en' => 'photo-essays', 'so' => 'sheeko-sawir']],
    ],

    /*
    | Editorial workflow (per language version).
    */
    'workflow' => [
        'draft' => ['submitted'],
        'submitted' => ['editing', 'draft'],
        'editing' => ['approved', 'draft'],
        'approved' => ['published', 'scheduled', 'editing'],
        'scheduled' => ['published', 'approved'],
        'published' => ['archived', 'editing'],
        'archived' => ['draft'],
    ],

    'media' => [
        'max_upload_kb' => 51200,
        'image_widths' => [480, 960, 1600],
        'allowed_mimes' => 'jpg,jpeg,png,webp,gif,mp4,webm,mp3,m4a,ogg,pdf',
    ],
];
