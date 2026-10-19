<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthorController extends Controller
{
    public function index()
    {
        $authors = Author::with('photo', 'user.role')->withCount('articles')
            ->withSum(['articles as views' => fn ($q) => $q->join('article_translations as t', 't.article_id', '=', 'articles.id')], 't.view_count')
            ->orderBy('team_order')->orderBy('name')->get();

        return view('admin.authors.index', compact('authors'));
    }

    public function create()
    {
        return view('admin.authors.form', ['author' => new Author(['is_staff' => true, 'show_on_team_page' => true, 'team_order' => 100]), 'users' => $this->users()]);
    }

    public function edit(Author $author)
    {
        return view('admin.authors.form', ['author' => $author, 'users' => $this->users($author)]);
    }

    protected function users(?Author $author = null)
    {
        return User::where(fn ($q) => $q->whereDoesntHave('author')->when($author?->user_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name']);
    }

    protected function data(Request $request, ?Author $author = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/', Rule::unique('authors', 'slug')->ignore($author?->id)],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('authors', 'user_id')->ignore($author?->id)],
            'title_en' => 'nullable|string|max:120', 'title_so' => 'nullable|string|max:120',
            'bio_en' => 'nullable|string|max:3000', 'bio_so' => 'nullable|string|max:3000',
            'photo_id' => 'nullable|exists:media_assets,id',
            'email' => 'nullable|email|max:190', 'x_handle' => 'nullable|string|max:60', 'linkedin_url' => 'nullable|url|max:255',
            'desk' => 'nullable|string|max:80', 'team_order' => 'nullable|integer|min:0|max:9999',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_staff'] = $request->boolean('is_staff');
        $data['show_on_team_page'] = $request->boolean('show_on_team_page');
        $data['team_order'] ??= 100;

        return $data;
    }

    public function store(Request $request)
    {
        $author = Author::create($this->data($request));
        AuditLog::record('author.created', "Created profile {$author->name}", $author);

        return redirect()->route('admin.authors.index')->with('status', __('Profile created.'));
    }

    public function update(Request $request, Author $author)
    {
        $author->update($this->data($request, $author));
        AuditLog::record('author.updated', "Updated profile {$author->name}", $author);

        return redirect()->route('admin.authors.index')->with('status', __('Profile saved.'));
    }

    public function destroy(Author $author)
    {
        if ($author->articles()->exists()) {
            return back()->withErrors(['author' => __('Reassign this journalist’s stories before deleting the profile.')]);
        }
        $author->delete();

        return back()->with('status', __('Profile deleted.'));
    }
}
