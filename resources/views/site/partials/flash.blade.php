@if (session('status'))
    <div class="mb-5 rounded-sm border-l-4 border-[var(--accent-bright)] bg-[var(--bg-2)] p-4 text-sm font-bold" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-sm border-l-4 border-[var(--live)] bg-[var(--bg-2)] p-4 text-sm" role="alert">
        <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
