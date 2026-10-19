<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleTranslation;
use App\Models\AuditLog;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public function index()
    {
        $live = ArticleTranslation::where('status', 'published');

        return view('admin.seo', [
            'redirects' => Redirect::latest()->paginate(30),
            'issues' => [
                'missing_description' => (clone $live)->whereNull('seo_description')->whereNull('standfirst')->count(),
                'long_title' => (clone $live)->whereRaw('LENGTH(COALESCE(seo_title, title)) > 70')->count(),
                'no_image' => (clone $live)->whereHas('article', fn ($q) => $q->whereNull('featured_media_id'))->count(),
                'noindex' => (clone $live)->where('noindex', true)->count(),
            ],
            'needsWork' => (clone $live)->where(fn ($q) => $q->whereNull('seo_description')->whereNull('standfirst')
                ->orWhereRaw('LENGTH(COALESCE(seo_title, title)) > 70'))->with('article')->latest('published_at')->limit(15)->get(),
            'settings' => [
                'google_site_verification' => setting('google_site_verification'),
                'bing_site_verification' => setting('bing_site_verification'),
                'google_analytics_id' => setting('google_analytics_id'),
                'robots_txt' => setting('robots_txt'),
                'block_indexing' => (bool) setting('block_indexing'),
                'default_og_image' => setting('default_og_image'),
            ],
        ]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate([
            'google_site_verification' => 'nullable|string|max:120',
            'bing_site_verification' => 'nullable|string|max:120',
            'google_analytics_id' => ['nullable', 'string', 'max:30', 'regex:/^(G|UA)-[A-Z0-9\-]+$/'],
            'robots_txt' => 'nullable|string|max:4000',
            'default_og_image' => 'nullable|url|max:255',
        ]);
        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }
        Setting::put('block_indexing', $request->boolean('block_indexing'));
        AuditLog::record('seo.settings', 'Updated SEO settings');

        return back()->with('status', __('SEO settings saved.'));
    }

    public function storeRedirect(Request $request)
    {
        $data = $request->validate([
            'from_path' => 'required|string|max:255',
            'to_url' => 'required|string|max:255',
            'status_code' => 'required|in:301,302',
        ]);
        $data['from_path'] = Redirect::normalize($data['from_path']);
        abort_if(in_array($data['from_path'], ['/', '/admin', '/en', '/so'], true), 422, 'Protected path');
        Redirect::updateOrCreate(['from_path' => $data['from_path']], $data);
        Cache::forget('redirects.map');
        AuditLog::record('seo.redirect', "Redirect {$data['from_path']} → {$data['to_url']}");

        return back()->with('status', __('Redirect saved.'));
    }

    public function destroyRedirect(Redirect $redirect)
    {
        $redirect->delete();
        Cache::forget('redirects.map');

        return back()->with('status', __('Redirect removed.'));
    }
}
