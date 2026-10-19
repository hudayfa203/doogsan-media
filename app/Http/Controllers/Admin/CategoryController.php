<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::with('translations', 'parent.translations')->withCount('articles')->orderBy('position')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true, 'in_navigation' => true, 'position' => 100]), 'parents' => Category::with('translations')->whereNull('parent_id')->get()]);
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category->load('translations'), 'parents' => Category::with('translations')->whereNull('parent_id')->where('id', '!=', $category->id)->get()]);
    }

    protected function save(Request $request, Category $category)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/', Rule::unique('categories', 'key')->ignore($category->id)],
            'parent_id' => 'nullable|exists:categories,id',
            'color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'locale' => 'nullable|in:en,so',
            'position' => 'nullable|integer|min:0|max:9999',
            't' => 'required|array',
            't.*.name' => 'required|string|max:80',
            't.*.slug' => 'nullable|string|max:80|regex:/^[a-z0-9\-]*$/',
            't.*.description' => 'nullable|string|max:255',
            't.*.seo_title' => 'nullable|string|max:120',
            't.*.seo_description' => 'nullable|string|max:255',
        ]);

        $category->fill([
            'key' => $data['key'], 'parent_id' => $data['parent_id'] ?? null, 'color' => $data['color'] ?? null, 'locale' => $data['locale'] ?? null,
            'position' => $data['position'] ?? 100, 'is_active' => $request->boolean('is_active'), 'in_navigation' => $request->boolean('in_navigation'),
        ])->save();

        foreach ($data['t'] as $locale => $t) {
            if (! array_key_exists($locale, editions())) {
                continue;
            }
            $slug = ($t['slug'] ?? null) ?: Str::slug($t['name']);
            $reserved = array_merge(array_column(config('doogsan.segments'), $locale), collect(config('doogsan.formats'))->pluck("slug.{$locale}")->all());
            if (in_array($slug, $reserved, true)) {
                $slug .= '-news';
            }
            $category->translations()->updateOrCreate(['locale' => $locale], ['name' => $t['name'], 'slug' => $slug,
                'description' => $t['description'] ?? null, 'seo_title' => $t['seo_title'] ?? null, 'seo_description' => $t['seo_description'] ?? null]);
        }
        AuditLog::record('category.saved', "Saved section {$category->key}", $category);
        foreach (array_keys(editions()) as $l) {
            app(\App\Services\HomepageService::class)->flush($l);
        }

        return redirect()->route('admin.categories.index')->with('status', __('Section saved.'));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Category);
    }

    public function update(Request $request, Category $category)
    {
        return $this->save($request, $category);
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->exists()) {
            return back()->withErrors(['category' => __('Move this section’s stories elsewhere before deleting it.')]);
        }
        $category->delete();

        return back()->with('status', __('Section deleted.'));
    }

    public function tags(Request $request)
    {
        $tags = Tag::withCount('translations')
            ->when($request->input('locale'), fn ($q, $l) => $q->where('locale', $l))
            ->when($request->input('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('translations_count')->paginate(60)->withQueryString();

        return view('admin.categories.tags', compact('tags'));
    }

    public function destroyTag(Tag $tag)
    {
        $tag->translations()->detach();
        $tag->delete();

        return back()->with('status', __('Tag removed.'));
    }
}
