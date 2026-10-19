@php $existing = $channel->exists; @endphp
<div x-data="{ type: @js($channel->source_type ?? 'hls') }" class="contents">
    <div><label class="label">Channel name</label><input name="name" value="{{ $channel->name }}" required maxlength="120" class="input"></div>
    <div><label class="label">Source type</label>
        <select name="source_type" x-model="type" class="select">
            <option value="hls">HLS stream (.m3u8)</option>
            <option value="youtube">YouTube Live</option>
            <option value="external">External player URL</option>
        </select></div>
    <div x-show="type !== 'youtube'">
        <label class="label">Stream URL</label>
        <input name="source_url" type="url" class="input" maxlength="500" placeholder="{{ $existing && filled($channel->source_url) ? '•••••• stored — leave blank to keep' : 'https://…/live/index.m3u8' }}">
        <p class="hint">Stored encrypted. Only the playback URL is sent to readers' browsers.</p>
    </div>
    <div x-show="type === 'youtube'">
        <label class="label">YouTube video ID or URL</label>
        <input name="youtube_video_id" value="{{ $channel->youtube_video_id }}" class="input" maxlength="64" placeholder="https://www.youtube.com/live/…">
    </div>
    <div class="md:col-span-2">
        <label class="label">Ingest notes (private)</label>
        <textarea name="ingest_notes" rows="2" class="textarea" maxlength="2000" placeholder="{{ $existing && filled($channel->ingest_notes) ? '•••••• stored — leave blank to keep' : 'RTMP server, stream key location, encoder settings…' }}"></textarea>
        <p class="hint">Encrypted, never shown publicly. Do not paste passwords you also use elsewhere.</p>
    </div>
    <x-admin.media-input name="poster_media_id" :value="$channel->poster_media_id" label="Off-air poster" />
    <div class="space-y-2 self-end">
        <input type="hidden" name="is_enabled" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" class="check" @checked($channel->is_enabled ?? true)> Channel enabled</label>
        <input type="hidden" name="show_live_indicator" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_live_indicator" value="1" class="check" @checked($channel->show_live_indicator ?? true)> Show LIVE badge in site header while on air</label>
    </div>
</div>
