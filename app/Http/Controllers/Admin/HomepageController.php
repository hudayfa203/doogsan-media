<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\HomepageLayout;
use App\Services\HomepageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomepageController extends Controller
{
    public function edit(HomepageService $home, string $locale = 'en')
    {
        $layout = $home->layout($locale);
        $active = HomepageLayout::activeFor($locale);
        $pickIds = array_filter(array_merge([$layout['lead']], (array) $layout['secondary']));
        $picks = \App\Models\Article::with('translations')->whereIn('id', $pickIds)->get()->keyBy('id');

        return view('admin.homepage', [
            'locale' => $locale,
            'layout' => $layout,
            'active' => $active,
            'scheduled' => HomepageLayout::where('locale', $locale)->where('active_from', '>', now())->orderBy('active_from')->get(),
            'history' => HomepageLayout::with('creator')->where('locale', $locale)->where('active_from', '<=', now())->latest('active_from')->limit(8)->get(),
            'categories' => Category::with('translations')->orderBy('position')->get(),
            'picks' => $picks->map(fn ($a) => ['id' => $a->id, 'title' => $a->t($locale)?->title ?? $a->displayTitle()]),
            'preview' => $home->build($locale),
        ]);
    }

    public function save(Request $request, HomepageService $home, string $locale)
    {
        $data = $request->validate([
            'layout' => 'required|json',
            'label' => 'nullable|string|max:120',
            'active_from' => 'nullable|date',
        ]);
        $layout = json_decode($data['layout'], true);
        $clean = [
            'lead' => isset($layout['lead']) && $layout['lead'] ? (int) $layout['lead'] : null,
            'secondary' => array_values(array_slice(array_map('intval', array_filter((array) ($layout['secondary'] ?? []))), 0, 4)),
            'show_live_tv' => (bool) ($layout['show_live_tv'] ?? true),
            'sections' => collect($layout['sections'] ?? [])->filter(fn ($s) => isset(HomepageService::SECTION_TYPES[$s['type'] ?? '']))
                ->map(fn ($s) => array_intersect_key($s, array_flip(['type', 'key', 'slot', 'title', 'style', 'limit', 'enabled'])))
                ->map(function ($s) {
                    $s['enabled'] = (bool) ($s['enabled'] ?? true);
                    $s['limit'] = max(1, min(12, (int) ($s['limit'] ?? 4)));

                    return $s;
                })->values()->all(),
        ];

        $from = ! empty($data['active_from']) ? Carbon::parse($data['active_from'], config('doogsan.timezone'))->utc() : now();
        $v = HomepageLayout::create(['locale' => $locale, 'label' => $data['label'] ?? null, 'layout' => $clean, 'active_from' => $from, 'created_by' => $request->user()->id]);
        AuditLog::record('homepage.saved', "Homepage ({$locale}) ".($from->isFuture() ? 'scheduled for '.news_date($from) : 'updated'), $v);
        $home->flush($locale);

        return back()->with('status', $from->isFuture() ? __('Layout scheduled for :d.', ['d' => news_date($from)]) : __('Homepage updated — live now.'));
    }

    public function destroy(HomepageLayout $layout, HomepageService $home)
    {
        $layout->delete();
        $home->flush($layout->locale);

        return back()->with('status', __('Layout version removed.'));
    }
}
