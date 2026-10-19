@extends('layouts.admin')
@section('title', 'Message from '.$message->name)

@section('content')
<x-admin.page-header eyebrow="Messages" :title="$message->subject ?: 'Message from '.$message->name">
    <a href="{{ route('admin.inbox.index') }}" class="btn btn-ghost">← Inbox</a>
</x-admin.page-header>

<div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
    <section class="panel">
        <div class="panel-head">
            <h2>{{ $message->name }} <span class="font-normal text-[var(--color-ink-400)]">&lt;{{ $message->email }}&gt;</span></h2>
            <span class="num text-xs text-[var(--color-ink-400)]">{{ local_time($message->created_at)->format('d M Y H:i') }} EAT</span>
        </div>
        <div class="panel-body whitespace-pre-line text-sm leading-relaxed text-[var(--color-ink-100)]" lang="{{ $message->locale }}">{{ $message->message }}</div>
    </section>
    <aside class="space-y-4">
        <section class="panel"><div class="panel-body space-y-3 text-sm">
            <div><p class="eyebrow">Edition</p><p>{{ editions()[$message->locale]['name'] ?? $message->locale }}</p></div>
            <div><p class="eyebrow">Department</p><p>{{ ucfirst($message->department ?? 'general') }}</p></div>
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'Your message to Doogsan Media')) }}" class="btn btn-primary w-full">Reply by email</a>
        </div></section>
        <form method="post" action="{{ route('admin.inbox.message.update', $message) }}" class="panel panel-body space-y-3">
            @csrf @method('PATCH')
            <label class="label">Status</label>
            <select name="status" class="select">@foreach (['new', 'read', 'replied', 'archived'] as $s)<option value="{{ $s }}" @selected($message->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
            <button class="btn btn-secondary w-full">Update</button>
        </form>
    </aside>
</div>
@endsection
