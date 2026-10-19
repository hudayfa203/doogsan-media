@props(['name', 'value' => null, 'label' => 'Image', 'type' => 'image'])
@php $m = $value ? \App\Models\MediaAsset::find($value) : null; @endphp
<div x-data="mediaField({{ $m?->id ?? 'null' }}, @js($m?->url(480)), @js($type))">
    <label class="label">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="id">
    <div class="flex items-center gap-3">
        <button type="button" @click="choose" class="relative grid h-20 w-32 flex-none place-items-center overflow-hidden rounded-lg border border-dashed border-[var(--color-ink-600)] bg-[var(--color-ink-850)] text-xs font-bold text-[var(--color-ink-400)] hover:border-[var(--color-signal)]">
            <template x-if="url"><img :src="url" class="absolute inset-0 h-full w-full object-cover" alt=""></template>
            <span x-show="!url">+ {{ __('Choose') }}</span>
        </button>
        <div class="flex flex-col gap-1">
            <button type="button" @click="choose" class="btn btn-secondary btn-sm">{{ __('Library') }}</button>
            <button type="button" x-show="id" @click="clear" class="btn btn-ghost btn-sm">{{ __('Remove') }}</button>
        </div>
    </div>
</div>
