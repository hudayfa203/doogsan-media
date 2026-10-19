<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public const LOCATIONS = [
        'primary' => 'Main navigation',
        'footer_news' => 'Footer — News',
        'footer_journalism' => 'Footer — Journalism',
        'footer_company' => 'Footer — Doogsan Media',
        'footer_legal' => 'Footer — Policies',
    ];

    public function edit(string $locale = 'en')
    {
        $menus = collect(self::LOCATIONS)->mapWithKeys(function ($label, $loc) use ($locale) {
            $menu = Menu::firstOrCreate(['locale' => $locale, 'location' => $loc], ['name' => $label]);

            return [$loc => $menu->allItems()->get(['id', 'label', 'link_type', 'link_value', 'position', 'open_new_tab', 'highlight'])];
        });

        return view('admin.menus', [
            'locale' => $locale,
            'menus' => $menus,
            'categories' => Category::with('translations')->orderBy('position')->get(),
            'pages' => Page::with('translations')->get(),
            'routes' => ['home', 'latest', 'breaking', 'live', 'tv', 'videos', 'documentaries', 'podcasts', 'galleries', 'media', 'authors', 'team', 'search', 'archive', 'contact', 'tip', 'newsletter'],
        ]);
    }

    public function save(Request $request, string $locale)
    {
        $data = $request->validate(['menus' => 'required|json']);
        $menus = json_decode($data['menus'], true);

        DB::transaction(function () use ($menus, $locale) {
            foreach (self::LOCATIONS as $loc => $label) {
                $menu = Menu::firstOrCreate(['locale' => $locale, 'location' => $loc], ['name' => $label]);
                $menu->allItems()->delete();
                foreach (array_values($menus[$loc] ?? []) as $i => $item) {
                    if (blank($item['label'] ?? null) || blank($item['link_value'] ?? null)) {
                        continue;
                    }
                    $type = in_array($item['link_type'] ?? 'url', ['url', 'route', 'category', 'page', 'format'], true) ? $item['link_type'] : 'url';
                    if ($type === 'url' && ! preg_match('~^(https?://|/)~', $item['link_value'])) {
                        continue;
                    }
                    $menu->allItems()->create([
                        'label' => mb_substr($item['label'], 0, 80), 'link_type' => $type, 'link_value' => mb_substr($item['link_value'], 0, 255),
                        'position' => $i, 'open_new_tab' => (bool) ($item['open_new_tab'] ?? false), 'highlight' => (bool) ($item['highlight'] ?? false),
                    ]);
                }
            }
        });
        Cache::forget("menus.{$locale}");
        AuditLog::record('menus.updated', "Updated navigation ({$locale})");

        return back()->with('status', __('Navigation saved.'));
    }
}
