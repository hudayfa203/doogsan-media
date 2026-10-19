<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $videos = Video::with('thumbnail')
            ->when($request->input('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->input('locale'), fn ($q, $l) => $q->where('locale', $l))
            ->latest()->paginate(24)->withQueryString();

        return view('admin.videos.index', compact('videos'));
    }

    public function create()
    {
        return $this->form(new Video(['locale' => 'en', 'kind' => 'video', 'provider' => 'youtube', 'status' => 'draft']));
    }

    public function edit(Video $video)
    {
        return $this->form($video);
    }

    protected function form(Video $video)
    {
        return view('admin.videos.form', [
            'video' => $video,
            'categories' => Category::with('translations')->orderBy('position')->get(),
            'authors' => Author::orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function data(Request $request, Video $video): array
    {
        $data = $request->validate([
            'locale' => 'required|in:en,so',
            'kind' => 'required|in:video,documentary,interview,podcast',
            'title' => 'required|string|max:200',
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]*$/'],
            'description' => 'nullable|string|max:3000',
            'provider' => 'required|in:youtube,hls,file,audio',
            'source_url' => 'nullable|url|max:255',
            'youtube_id' => 'nullable|string|max:120',
            'media_id' => 'nullable|exists:media_assets,id',
            'thumbnail_media_id' => 'nullable|exists:media_assets,id',
            'category_id' => 'nullable|exists:categories,id',
            'author_id' => 'nullable|exists:authors,id',
            'duration' => 'nullable|string|max:10',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ]);
        if (! empty($data['youtube_id']) && preg_match('~(?:v=|youtu\.be/|embed/|shorts/|live/)([A-Za-z0-9_-]{11})~', $data['youtube_id'], $m)) {
            $data['youtube_id'] = $m[1];
        }
        $slug = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        $base = $slug;
        $i = 2;
        while (Video::where('locale', $data['locale'])->where('slug', $slug)->where('id', '!=', $video->id ?? 0)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $data['slug'] = $slug;
        if (! empty($data['duration'])) {
            $parts = array_reverse(array_map('intval', explode(':', $data['duration'])));
            $data['duration_seconds'] = ($parts[0] ?? 0) + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0) * 3600;
        }
        unset($data['duration']);
        $data['published_at'] = $data['status'] === 'published'
            ? (! empty($data['published_at']) ? Carbon::parse($data['published_at'], config('doogsan.timezone'))->utc() : ($video->published_at ?? now()))
            : null;
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    public function store(Request $request)
    {
        $video = Video::create($this->data($request, new Video));
        AuditLog::record('video.created', "Added {$video->kind}: {$video->title}", $video);
        app(\App\Services\HomepageService::class)->flush($video->locale);

        return redirect()->route('admin.videos.index')->with('status', __('Saved.'));
    }

    public function update(Request $request, Video $video)
    {
        $video->update($this->data($request, $video));
        AuditLog::record('video.updated', "Updated {$video->kind}: {$video->title}", $video);
        app(\App\Services\HomepageService::class)->flush($video->locale);

        return redirect()->route('admin.videos.index')->with('status', __('Saved.'));
    }

    public function destroy(Video $video)
    {
        $video->delete();
        AuditLog::record('video.deleted', "Deleted {$video->title}", $video);

        return back()->with('status', __('Deleted.'));
    }
}
