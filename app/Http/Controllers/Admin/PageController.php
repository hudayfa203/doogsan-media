<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Page;
use App\Services\BodyRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index()
    {
        return view('admin.pages.index', ['pages' => Page::with('translations')->orderBy('key')->get()]);
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', ['page' => $page->load('translations')]);
    }

    public function update(Request $request, Page $page, BodyRenderer $renderer)
    {
        $data = $request->validate([
            't' => 'required|array',
            't.*.title' => 'required|string|max:160',
            't.*.slug' => 'nullable|string|max:120|regex:/^[a-z0-9\-]*$/',
            't.*.summary' => 'nullable|string|max:500',
            't.*.body' => 'nullable|string',
            't.*.seo_description' => 'nullable|string|max:255',
        ]);
        foreach ($data['t'] as $locale => $t) {
            if (! array_key_exists($locale, editions())) {
                continue;
            }
            $doc = json_decode($t['body'] ?? '', true);
            $page->translations()->updateOrCreate(['locale' => $locale], [
                'title' => $t['title'],
                'slug' => $t['slug'] ?: Str::slug($t['title']),
                'summary' => $t['summary'] ?? null,
                'body_html' => is_array($doc) ? $renderer->render($doc, $locale)['html'] : $page->t($locale)?->body_html,
                'seo_description' => $t['seo_description'] ?? null,
                'is_published' => $request->boolean("published.{$locale}"),
            ]);
        }
        $page->update(['show_in_footer' => $request->boolean('show_in_footer')]);
        AuditLog::record('page.updated', "Updated page {$page->key}", $page);

        return redirect()->route('admin.pages.index')->with('status', __('Page saved.'));
    }
}
