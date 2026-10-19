<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    protected function query(Request $request)
    {
        return MediaAsset::query()
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('folder'), fn ($q, $f) => $q->where('folder', $f))
            ->when(trim((string) $request->input('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")
                ->orWhere('original_name', 'like', "%{$s}%")->orWhere('credit', 'like', "%{$s}%")->orWhere('alt_en', 'like', "%{$s}%")))
            ->latest();
    }

    public function index(Request $request)
    {
        return view('admin.media', [
            'assets' => $this->query($request)->with('uploader')->paginate(36)->withQueryString(),
            'folders' => MediaAsset::whereNotNull('folder')->distinct()->pluck('folder'),
            'totals' => MediaAsset::selectRaw('type, COUNT(*) as c, SUM(size_bytes) as bytes')->groupBy('type')->get()->keyBy('type'),
        ]);
    }

    /** JSON for the media picker modal used across the CMS. */
    public function picker(Request $request)
    {
        return $this->query($request)->paginate(24)->through(fn (MediaAsset $m) => [
            'id' => $m->id, 'type' => $m->type, 'url' => $m->url(480), 'full' => $m->url(1600), 'title' => $m->title,
            'credit' => $m->credit, 'alt' => $m->alt_en, 'width' => $m->width, 'height' => $m->height,
        ]);
    }

    public function store(Request $request, MediaService $media)
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|max:'.config('doogsan.media.max_upload_kb').'|mimes:'.config('doogsan.media.allowed_mimes'),
            'credit' => 'nullable|string|max:190',
            'folder' => 'nullable|string|max:60',
        ]);
        $assets = collect($request->file('files'))->map(fn ($f) => $media->store($f, $request->user(), $request->only('credit', 'folder')));

        if ($request->expectsJson()) {
            return response()->json($assets->map(fn ($m) => ['id' => $m->id, 'type' => $m->type, 'url' => $m->url(480), 'full' => $m->url(1600), 'title' => $m->title]));
        }

        return back()->with('status', __(':n file(s) uploaded.', ['n' => $assets->count()]));
    }

    public function update(Request $request, MediaAsset $media)
    {
        $media->update($request->validate([
            'title' => 'nullable|string|max:190',
            'alt_en' => 'nullable|string|max:250',
            'alt_so' => 'nullable|string|max:250',
            'caption_en' => 'nullable|string|max:1000',
            'caption_so' => 'nullable|string|max:1000',
            'credit' => 'nullable|string|max:190',
            'copyright' => 'nullable|string|max:190',
            'license' => 'nullable|string|max:190',
            'usage_notes' => 'nullable|string|max:250',
            'folder' => 'nullable|string|max:60',
        ]));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', __('Media details saved.'));
    }

    public function destroy(MediaAsset $media, MediaService $service)
    {
        $inUse = \App\Models\Article::where('featured_media_id', $media->id)->exists()
            || \Illuminate\Support\Facades\DB::table('article_media')->where('media_asset_id', $media->id)->exists();
        if ($inUse) {
            return back()->withErrors(['media' => __('This file is used by a story. Replace it there first.')]);
        }
        $service->delete($media);

        return back()->with('status', __('File deleted.'));
    }
}
