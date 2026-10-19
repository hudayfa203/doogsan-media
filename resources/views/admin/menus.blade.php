@extends('layouts.admin')
@section('title', 'Navigation — '.editions()[$locale]['name'])

@php
    $ed = editions()[$locale];
    $labels = \App\Http\Controllers\Admin\MenuController::LOCATIONS;
    $options = [
        'route' => collect($routes)->mapWithKeys(fn ($r) => [$r => ucfirst(str_replace('_', ' ', $r))]),
        'category' => $categories->filter(fn ($c) => ! $c->locale || $c->locale === $locale)->mapWithKeys(fn ($c) => [$c->key => $c->nameFor($locale)]),
        'format' => collect(config('doogsan.formats'))->map(fn ($f) => $f[$locale] ?? $f['en']),
        'page' => $pages->mapWithKeys(fn ($p) => [$p->key => $p->t($locale)?->title ?? $p->key]),
    ];
@endphp

@section('content')
<x-admin.page-header :eyebrow="$ed['name'].' edition'" title="Navigation" subtitle="Main menu and footer links for this edition. Links to sections, formats and pages always use this edition’s own URLs." />

<div class="mb-5 flex flex-wrap gap-1 border-b border-[var(--color-ink-700)]">
    @foreach (editions() as $l => $e)
        <a href="{{ route('admin.menus.edit', $l) }}" class="tab {{ $l === $locale ? 'is-active' : '' }}">{{ $e['name'] }}</a>
    @endforeach
</div>

<form method="post" action="{{ route('admin.menus.save', $locale) }}" x-data="menuBuilder(@js($menus))" @submit="$refs.menus.value = JSON.stringify(menus)">
    @csrf
    <input type="hidden" name="menus" x-ref="menus">
    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <nav class="panel h-max p-2">
            @foreach ($labels as $k => $v)
                <button type="button" @click="loc = '{{ $k }}'" class="nav-item w-full text-left" :class="loc === '{{ $k }}' && 'is-active'">
                    <span class="flex-1">{{ $v }}</span><span class="num text-xs text-[var(--color-ink-500)]" x-text="menus['{{ $k }}'].length"></span>
                </button>
            @endforeach
        </nav>

        <section class="panel">
            <div class="panel-head"><h2 x-text="@js($labels)[loc]"></h2>
                <div class="flex gap-2"><button type="button" class="btn btn-secondary btn-sm" @click="add()"><x-admin.icon name="plus" /> Add link</button><button class="btn btn-primary btn-sm">Save navigation</button></div></div>
            <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-8"></th><th>Label</th><th>Links to</th><th>Target</th><th class="text-center">New tab</th><th class="text-center">Highlight</th><th></th></tr></thead>
                <tbody>
                    <template x-for="(item, i) in menus[loc]" :key="loc + i">
                        <tr>
                            <td class="num text-xs text-[var(--color-ink-500)]" x-text="i + 1"></td>
                            <td><input x-model="item.label" class="input !w-48" maxlength="80" placeholder="Label" lang="{{ $locale }}"></td>
                            <td><select x-model="item.link_type" class="select !w-32" @change="item.link_value = ''">
                                <option value="route">Site page</option><option value="category">Section</option><option value="format">Format</option><option value="page">Info page</option><option value="url">Custom URL</option></select></td>
                            <td>
                                @foreach ($options as $type => $opts)
                                    <template x-if="item.link_type === '{{ $type }}'">
                                        <select x-model="item.link_value" class="select !w-52"><option value="">Choose…</option>@foreach ($opts as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                                    </template>
                                @endforeach
                                <template x-if="item.link_type === 'url'"><input x-model="item.link_value" class="input !w-52" placeholder="https://… or /{{ $locale }}/…"></template>
                            </td>
                            <td class="text-center"><input type="checkbox" class="check" x-model="item.open_new_tab"></td>
                            <td class="text-center"><input type="checkbox" class="check" x-model="item.highlight"></td>
                            <td class="whitespace-nowrap text-right">
                                <button type="button" class="btn btn-ghost btn-sm" @click="move(i, -1)" aria-label="Move up">↑</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="move(i, 1)" aria-label="Move down">↓</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="remove(i)" aria-label="Remove">✕</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!menus[loc].length"><td colspan="7" class="py-6 text-center text-sm text-[var(--color-ink-400)]">No links in this menu yet.</td></tr>
                </tbody>
            </table>
            </div>
            <p class="panel-body hint">Rows with an empty label or target are skipped when saving. Custom URLs must start with https:// or /.</p>
        </section>
    </div>
</form>
@endsection
