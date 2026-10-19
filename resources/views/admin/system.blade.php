@extends('layouts.admin')
@section('title', 'System health')

@section('content')
@php
    $labels = ['database' => 'Database', 'cache' => 'Cache', 'queue' => 'Queue', 'scheduler' => 'Scheduler (cron)', 'broadcasting' => 'Real-time broadcasting', 'storage' => 'Storage', 'streams' => 'Live TV streams', 'backups' => 'Backups', 'php' => 'PHP & Laravel'];
    $tone = ['ok' => ['pill-published', 'OK'], 'warn' => ['pill-editing', 'Attention'], 'fail' => ['pill-live', 'Failing']];
    $summary = collect($checks)->countBy('status');
@endphp
<x-admin.page-header eyebrow="Administration" title="System health" :subtitle="'Checked '.local_time(now())->format('d M Y H:i').' EAT. Reload to check again.'">
    <a href="{{ route('admin.system') }}" class="btn btn-secondary">Re-run checks</a>
</x-admin.page-header>

<div class="mb-6 grid grid-cols-3 gap-3 sm:max-w-lg">
    @foreach (['ok' => 'Healthy', 'warn' => 'Attention', 'fail' => 'Failing'] as $k => $lbl)
        <div class="panel px-4 py-3"><p class="eyebrow">{{ $lbl }}</p><p class="num text-2xl font-bold {{ $k === 'fail' && ($summary[$k] ?? 0) ? 'text-[var(--color-alarm)]' : ($k === 'ok' ? 'text-[var(--color-signal)]' : 'text-white') }}">{{ $summary[$k] ?? 0 }}</p></div>
    @endforeach
</div>

<div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
    <section class="panel">
        <div class="panel-head"><h2>Checks</h2></div>
        <div class="divide-y divide-[var(--color-ink-800)]">
            @foreach ($checks as $key => $c)
                @php [$cls, $word] = $tone[$c['status']] ?? ['pill-draft', $c['status']]; @endphp
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <span class="w-48 flex-none text-sm font-bold text-white">{{ $labels[$key] ?? \Illuminate\Support\Str::headline($key) }}</span>
                    <span class="pill {{ $cls }}">{{ $word }}</span>
                    <span class="min-w-0 flex-1 text-xs text-[var(--color-ink-300)]">{{ $c['detail'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Environment</h2></div>
        <dl class="divide-y divide-[var(--color-ink-800)] text-xs">
            @foreach ($env as $k => $v)
                <div class="flex justify-between gap-3 px-4 py-2.5"><dt class="text-[var(--color-ink-400)]">{{ $k }}</dt><dd class="num text-right {{ str_starts_with((string) $v, 'ON') ? 'font-bold text-[var(--color-alarm)]' : 'text-[var(--color-ink-100)]' }}">{{ $v }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>

<section class="panel mt-6">
    <div class="panel-head"><h2>Recent failed jobs</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ $failed->count() }}</span></div>
    @if ($failed->isEmpty())
        <p class="p-5 text-sm text-[var(--color-ink-400)]">No failed background jobs.</p>
    @else
        <table class="table"><thead><tr><th>Failed at</th><th>Queue</th><th>Error</th></tr></thead><tbody>
            @foreach ($failed as $j)
                <tr><td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time(\Illuminate\Support\Carbon::parse($j->failed_at))->format('d M H:i') }}</td><td class="text-xs">{{ $j->queue }}</td><td class="max-w-2xl truncate text-xs text-[#ffb3ad]" title="{{ \Illuminate\Support\Str::limit($j->exception, 600) }}">{{ \Illuminate\Support\Str::limit(strtok($j->exception, "\n"), 160) }}</td></tr>
            @endforeach
        </tbody></table>
        <p class="px-4 py-3 text-xs text-[var(--color-ink-500)]">Retry from the server: <code class="num">php artisan queue:retry all</code></p>
    @endif
</section>
@endsection
