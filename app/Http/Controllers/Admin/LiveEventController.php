<?php

namespace App\Http\Controllers\Admin;

use App\Events\LiveEventStatusChanged;
use App\Events\LiveUpdatePosted;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\LiveEvent;
use App\Models\LiveEventUpdate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LiveEventController extends Controller
{
    public function index()
    {
        return view('admin.live.index', [
            'events' => LiveEvent::withCount('allUpdates')->with('journalists')->orderByRaw("CASE status WHEN 'live' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")->latest('starts_at')->paginate(20),
            'categories' => Category::with('translations')->orderBy('position')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'locale' => 'required|in:en,so',
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:600',
            'category_id' => 'nullable|exists:categories,id',
            'cover_media_id' => 'nullable|exists:media_assets,id',
            'starts_at' => 'nullable|date',
            'go_live' => 'nullable|boolean',
            'journalists' => 'nullable|array',
            'journalists.*' => 'exists:users,id',
        ]);
        $slug = Str::slug($data['title']) ?: Str::random(8);
        $base = $slug;
        $i = 2;
        while (LiveEvent::where('locale', $data['locale'])->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $event = LiveEvent::create([
            'locale' => $data['locale'], 'title' => $data['title'], 'slug' => $slug, 'summary' => $data['summary'] ?? null,
            'category_id' => $data['category_id'] ?? null, 'cover_media_id' => $data['cover_media_id'] ?? null,
            'starts_at' => ! empty($data['starts_at']) ? Carbon::parse($data['starts_at'], config('doogsan.timezone'))->utc() : now(),
            'status' => ! empty($data['go_live']) ? 'live' : 'scheduled',
            'created_by' => $request->user()->id,
        ]);
        $event->journalists()->sync(array_unique(array_merge($data['journalists'] ?? [], [$request->user()->id])));
        AuditLog::record('live.created', "Opened live coverage: {$event->title}", $event);
        if ($event->isLive()) {
            $this->changed($event);
        }

        return redirect()->route('admin.live.desk', $event)->with('status', __('Live coverage created.'));
    }

    public function desk(LiveEvent $event)
    {
        $event->load(['allUpdates.author', 'allUpdates.media', 'journalists', 'cover']);

        return view('admin.live.desk', [
            'event' => $event,
            'authors' => Author::orderBy('name')->get(['id', 'name', 'user_id']),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::with('translations')->orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, LiveEvent $event)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'summary' => 'nullable|string|max:600',
            'category_id' => 'nullable|exists:categories,id',
            'cover_media_id' => 'nullable|exists:media_assets,id',
            'show_on_homepage' => 'nullable|boolean',
            'journalists' => 'nullable|array',
            'journalists.*' => 'exists:users,id',
        ]);
        $event->update(collect($data)->except('journalists')->all() + ['show_on_homepage' => $request->boolean('show_on_homepage')]);
        $event->journalists()->sync($data['journalists'] ?? []);
        $this->changed($event, false);

        return back()->with('status', __('Coverage details saved.'));
    }

    public function status(Request $request, LiveEvent $event)
    {
        $data = $request->validate(['status' => 'required|in:scheduled,live,closed']);
        $event->update([
            'status' => $data['status'],
            'starts_at' => $data['status'] === 'live' && (! $event->starts_at || $event->starts_at->isFuture()) ? now() : $event->starts_at,
            'ended_at' => $data['status'] === 'closed' ? now() : null,
        ]);
        AuditLog::record('live.'.$data['status'], "Live coverage {$data['status']}: {$event->title}", $event);
        $this->changed($event);

        return back()->with('status', __('Coverage is now :s.', ['s' => $data['status']]));
    }

    protected function changed(LiveEvent $event, bool $statusEvent = true): void
    {
        Cache::forget("home.{$event->locale}");
        Cache::forget("pulse.{$event->locale}");
        if ($statusEvent) {
            safe_broadcast(new LiveEventStatusChanged($event));
        }
    }

    protected function authorizePost(Request $request, LiveEvent $event): void
    {
        $u = $request->user();
        abort_unless($u->hasPermission('articles.publish') || $event->journalists()->whereKey($u->id)->exists(), 403,
            __('Only journalists assigned to this coverage can post.'));
    }

    public function post(Request $request, LiveEvent $event)
    {
        $this->authorizePost($request, $event);
        abort_if($event->status === 'closed', 422, __('This coverage is closed.'));
        $data = $request->validate([
            'headline' => 'nullable|string|max:200',
            'body' => 'required|string|max:5000',
            'author_id' => 'nullable|exists:authors,id',
            'media_id' => 'nullable|exists:media_assets,id',
            'embed_url' => 'nullable|url|max:255',
            'is_pinned' => 'nullable|boolean',
            'is_key_event' => 'nullable|boolean',
        ]);
        $data['body'] = $this->cleanBody($data['body']);
        $data['author_id'] ??= Author::where('user_id', $request->user()->id)->value('id');
        $update = $event->allUpdates()->create($data + [
            'user_id' => $request->user()->id, 'posted_at' => now(), 'status' => 'published',
            'is_pinned' => $request->boolean('is_pinned'), 'is_key_event' => $request->boolean('is_key_event'),
        ]);
        safe_broadcast(new LiveUpdatePosted($update));
        Cache::forget("home.{$event->locale}");

        return $request->expectsJson() ? response()->json(['ok' => true, 'update' => $update->toBroadcastArray()]) : back()->with('status', __('Update posted.'));
    }

    public function edit(Request $request, LiveEventUpdate $update)
    {
        $this->authorizePost($request, $update->event);
        $data = $request->validate(['headline' => 'nullable|string|max:200', 'body' => 'required|string|max:5000']);
        $data['body'] = $this->cleanBody($data['body']);
        $update->update($data);
        safe_broadcast(new LiveUpdatePosted($update, 'edited'));

        return back()->with('status', __('Update edited.'));
    }

    public function pin(Request $request, LiveEventUpdate $update)
    {
        $this->authorizePost($request, $update->event);
        $update->update(['is_pinned' => ! $update->is_pinned]);
        safe_broadcast(new LiveUpdatePosted($update, 'edited'));

        return back();
    }

    public function remove(Request $request, LiveEventUpdate $update)
    {
        $this->authorizePost($request, $update->event);
        AuditLog::record('live.update_deleted', 'Removed live update from '.$update->event->title, $update);
        safe_broadcast(new LiveUpdatePosted($update, 'deleted'));
        $update->delete();

        return back()->with('status', __('Update removed.'));
    }

    /** Live posts allow simple formatting only: paragraphs, bold, italic, links. */
    protected function cleanBody(string $body): string
    {
        $body = strip_tags($body, '<p><br><strong><b><em><i><a><ul><ol><li><blockquote>');
        $body = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $body);
        $body = preg_replace('/(href\s*=\s*["\'])\s*(javascript|data|vbscript):[^"\']*/i', '$1#', $body);
        $body = preg_replace('/<(\w+)\s+(?!href)[^>]*>/', '<$1>', $body);
        if (! str_contains($body, '<p>')) {
            $body = collect(preg_split('/\n{2,}/', trim($body)))->map(fn ($p) => '<p>'.nl2br(trim($p)).'</p>')->implode('');
        }

        return $body;
    }
}
