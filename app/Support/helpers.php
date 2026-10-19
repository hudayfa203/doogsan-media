<?php

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

if (! function_exists('editions')) {
    /** @return array<string, array> */
    function editions(): array
    {
        return config('doogsan.editions');
    }
}

if (! function_exists('edition')) {
    /** Current edition code (en|so). */
    function edition(): string
    {
        $locale = app()->getLocale();

        return array_key_exists($locale, editions()) ? $locale : config('doogsan.default_edition');
    }
}

if (! function_exists('seg')) {
    /** Localized URL segment, e.g. seg('latest', 'so') → wararkii-ugu-dambeeyay */
    function seg(string $key, ?string $locale = null): string
    {
        return config("doogsan.segments.{$key}.".($locale ?? edition()), $key);
    }
}

if (! function_exists('lroute')) {
    /**
     * Route URL inside a language edition: lroute('latest') → /en/latest-news.
     * "format" and "page" share the edition's top-level slug resolver.
     */
    function lroute(string $name, array $params = [], ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= edition();
        $name = match ($name) {
            'format', 'page', 'category' => 'section',
            default => $name,
        };

        return route("{$locale}.{$name}", $params, $absolute);
    }
}

if (! function_exists('local_time')) {
    /** Convert a UTC timestamp to the editorial timezone (Africa/Mogadishu). */
    function local_time(CarbonInterface|string|null $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        return Carbon::parse($time)->setTimezone(config('doogsan.timezone'))->locale(edition());
    }
}

if (! function_exists('news_date')) {
    /** Human date in the active edition: "19 October 2026, 14:05 EAT" / "19 Oktoobar 2026, 14:05". */
    function news_date(CarbonInterface|string|null $time, bool $withTime = true): string
    {
        $t = local_time($time);
        if (! $t) {
            return '';
        }

        $month = __('month.'.$t->month);
        $date = $t->day.' '.$month.' '.$t->year;

        return $withTime ? $date.', '.$t->format('H:i').' EAT' : $date;
    }
}

if (! function_exists('rel_time')) {
    /** Relative time, localized. The front-end keeps these ticking. */
    function rel_time(CarbonInterface|string|null $time): string
    {
        if (! $time) {
            return '';
        }

        $diff = (int) max(0, now()->diffInSeconds(Carbon::parse($time), true));

        return match (true) {
            $diff < 60 => __('time.just_now'),
            $diff < 3600 => trans_choice('time.minutes', intdiv($diff, 60), ['n' => intdiv($diff, 60)]),
            $diff < 86400 => trans_choice('time.hours', intdiv($diff, 3600), ['n' => intdiv($diff, 3600)]),
            $diff < 86400 * 7 => trans_choice('time.days', intdiv($diff, 86400), ['n' => intdiv($diff, 86400)]),
            default => news_date($time, false),
        };
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('lsetting')) {
    /** Edition-specific setting, falling back to the shared key. */
    function lsetting(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $locale ??= edition();

        return Setting::get("{$key}_{$locale}", Setting::get($key, $default));
    }
}

if (! function_exists('safe_broadcast')) {
    /**
     * Broadcast without ever breaking an editorial action. If the socket
     * server is down the site falls back to polling (/api/{locale}/pulse).
     */
    function safe_broadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage(), ['event' => $event::class]);
        }
    }
}

if (! function_exists('format_label')) {
    function format_label(string $format, ?string $locale = null): string
    {
        return config("doogsan.formats.{$format}.".($locale ?? edition()), ucfirst($format));
    }
}

if (! function_exists('brand_logo')) {
    /** Logo variant URL — editable per edition in Admin → Settings → Branding. */
    function brand_logo(string $variant = 'light', ?string $locale = null): string
    {
        $locale ??= edition();
        $custom = setting("logo_{$variant}_{$locale}") ?: setting("logo_{$variant}");

        return $custom ?: asset("images/brand/doogsan-logo-{$variant}.png");
    }
}

if (! function_exists('story_edit_url')) {
    /** Admin editor URL for a story in a given newsroom (defaults to its own edition). */
    function story_edit_url(\App\Models\Article|int $article, ?string $edition = null): string
    {
        $model = $article instanceof \App\Models\Article ? $article : \App\Models\Article::withTrashed()->find($article);

        return route('admin.articles.edit', ['edition' => $edition ?? $model?->edition ?? 'en', 'article' => $model?->id ?? $article]);
    }
}
