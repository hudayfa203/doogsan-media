<?php

namespace App\Http\Controllers\Admin;

use App\Events\LiveTvStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BroadcastChannel;
use App\Models\BroadcastProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BroadcastController extends Controller
{
    public function index()
    {
        return view('admin.broadcast', [
            'channels' => BroadcastChannel::with('poster')->get(),
            'programs' => BroadcastProgram::with('channel')->where('ends_at', '>', now()->subDay())->orderBy('starts_at')->limit(40)->get(),
        ]);
    }

    protected function channelData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'source_type' => 'required|in:hls,youtube,external',
            'source_url' => 'nullable|url|max:500',
            'youtube_video_id' => ['nullable', 'string', 'max:64'],
            'ingest_notes' => 'nullable|string|max:2000',
            'poster_media_id' => 'nullable|exists:media_assets,id',
        ]);
        if (! empty($data['youtube_video_id']) && preg_match('~(?:v=|youtu\.be/|embed/|live/)([A-Za-z0-9_-]{11})~', $data['youtube_video_id'], $m)) {
            $data['youtube_video_id'] = $m[1];
        }
        $data['is_enabled'] = $request->boolean('is_enabled', true);
        $data['show_live_indicator'] = $request->boolean('show_live_indicator', true);
        // Leaving secret fields blank keeps the stored value.
        foreach (['source_url', 'ingest_notes'] as $secret) {
            if (blank($data[$secret] ?? null) && $request->routeIs('*.update')) {
                unset($data[$secret]);
            }
        }

        return $data;
    }

    public function storeChannel(Request $request)
    {
        $data = $this->channelData($request);
        $c = BroadcastChannel::create($data + ['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
        AuditLog::record('broadcast.channel_created', "Added channel {$c->name}", $c);

        return back()->with('status', __('Channel added.'));
    }

    public function updateChannel(Request $request, BroadcastChannel $channel)
    {
        $channel->update($this->channelData($request));
        AuditLog::record('broadcast.channel_updated', "Updated channel {$channel->name}", $channel);
        $this->changed($channel);

        return back()->with('status', __('Channel saved.'));
    }

    public function toggleLive(BroadcastChannel $channel)
    {
        if (! $channel->is_live && ! $channel->hasSource()) {
            return back()->withErrors(['channel' => __('Add a stream source before going live.')]);
        }
        $channel->update(['is_live' => ! $channel->is_live, 'went_live_at' => ! $channel->is_live ? now() : $channel->went_live_at]);
        AuditLog::record('broadcast.'.($channel->is_live ? 'on_air' : 'off_air'), "{$channel->name} ".($channel->is_live ? 'ON AIR' : 'off air'), $channel);
        $this->changed($channel);

        return back()->with('status', $channel->is_live ? __(':n is now ON AIR.', ['n' => $channel->name]) : __(':n is off air.', ['n' => $channel->name]));
    }

    public function check(BroadcastChannel $channel)
    {
        Artisan::call('newsroom:check-streams');

        return back()->with('status', __('Stream check: :s', ['s' => $channel->fresh()->health_status]));
    }

    public function destroyChannel(BroadcastChannel $channel)
    {
        $channel->delete();
        $this->changed($channel);

        return back()->with('status', __('Channel removed.'));
    }

    protected function changed(BroadcastChannel $channel): void
    {
        foreach (array_keys(editions()) as $l) {
            Cache::forget("pulse.{$l}");
            Cache::forget("home.{$l}");
        }
        safe_broadcast(new LiveTvStatusChanged($channel));
    }

    public function storeProgram(Request $request)
    {
        $data = $request->validate([
            'broadcast_channel_id' => 'required|exists:broadcast_channels,id',
            'title_en' => 'required|string|max:160', 'title_so' => 'required|string|max:160',
            'description_en' => 'nullable|string|max:600', 'description_so' => 'nullable|string|max:600',
            'presenter' => 'nullable|string|max:120',
            'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at',
            'repeat_days' => 'nullable|integer|min:0|max:30',
        ]);
        $tz = config('doogsan.timezone');
        $start = Carbon::parse($data['starts_at'], $tz)->utc();
        $end = Carbon::parse($data['ends_at'], $tz)->utc();
        $repeat = (int) ($data['repeat_days'] ?? 0);
        unset($data['repeat_days']);
        for ($i = 0; $i <= $repeat; $i++) {
            BroadcastProgram::create(array_merge($data, ['starts_at' => $start->copy()->addDays($i), 'ends_at' => $end->copy()->addDays($i)]));
        }

        return back()->with('status', __('Programme scheduled.'));
    }

    public function destroyProgram(BroadcastProgram $program)
    {
        $program->delete();

        return back()->with('status', __('Programme removed.'));
    }
}
