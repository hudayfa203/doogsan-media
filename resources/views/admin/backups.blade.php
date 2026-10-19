@extends('layouts.admin')
@section('title', 'Backups')

@section('content')
@php $fmt = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1).' MB' : round($b / 1024).' KB'; @endphp
<x-admin.page-header eyebrow="Administration" title="Backups" subtitle="Database backups run automatically every day at 01:30 EAT and are stored on the server. Download a copy regularly and keep it off the server.">
    <form method="post" action="{{ route('admin.backups.run') }}">@csrf<button class="btn btn-primary">Back up now</button></form>
</x-admin.page-header>

<div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Backup files</h2><span class="num text-xs text-[var(--color-ink-400)]">{{ count($files) }}</span></div>
            @if (empty($files))
                <p class="p-5 text-sm text-[var(--color-ink-400)]">No backup files yet. Run one now to check backups work on this server.</p>
            @else
                <table class="table"><thead><tr><th>File</th><th>Size</th><th>Created (EAT)</th><th></th></tr></thead><tbody>
                    @foreach ($files as $f)
                        <tr>
                            <td class="num text-xs text-white">{{ $f['name'] }}</td>
                            <td class="num text-xs text-[var(--color-ink-300)]">{{ $fmt($f['size']) }}</td>
                            <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time(\Illuminate\Support\Carbon::createFromTimestamp($f['time']))->format('d M Y H:i') }}</td>
                            <td class="text-right"><a href="{{ route('admin.backups.download', $f['name']) }}" class="btn btn-secondary btn-sm">Download</a></td>
                        </tr>
                    @endforeach
                </tbody></table>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Recent runs</h2></div>
            <table class="table"><tbody>
                @forelse ($runs as $r)
                    <tr>
                        <td class="w-24"><span class="pill {{ $r->status === 'success' ? 'pill-published' : 'pill-live' }}">{{ $r->status === 'success' ? 'Success' : 'Failed' }}</span></td>
                        <td class="num whitespace-nowrap text-xs text-[var(--color-ink-400)]">{{ local_time($r->created_at)->format('d M Y H:i') }}</td>
                        <td class="num text-xs text-[var(--color-ink-300)]">{{ $r->size_bytes ? $fmt($r->size_bytes) : '' }}</td>
                        <td class="text-xs text-[var(--color-ink-300)]">{{ $r->message ?: basename((string) $r->path) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-8 text-center text-sm text-[var(--color-ink-400)]">No backups have run yet.</td></tr>
                @endforelse
            </tbody></table>
        </section>
    </div>

    <div class="space-y-6">
        <section class="panel">
            <div class="panel-head"><h2>Retention</h2></div>
            <form method="post" action="{{ route('admin.backups.settings') }}" class="panel-body space-y-3">
                @csrf
                <div><label class="label">Keep backups for (days)</label><input type="number" name="retention" min="1" max="365" value="{{ old('retention', $retention) }}" class="input num !w-32"></div>
                <p class="hint">Older files are deleted after each new backup.</p>
                <button class="btn btn-secondary btn-sm">Save</button>
            </form>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Also recommended</h2></div>
            <ul class="panel-body list-disc space-y-1.5 pl-8 text-xs text-[var(--color-ink-300)]">
                <li>Turn on your host’s daily account backups (cPanel → Backup / JetBackup).</li>
                <li>Download a copy weekly and store it off the server.</li>
                <li>Uploaded images live in <code class="num">storage/app/public</code> — include them in host backups.</li>
            </ul>
        </section>
    </div>
</div>
@endsection
