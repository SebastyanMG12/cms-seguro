<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\Audit;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = $request->user()->posts()
            ->latest()
            ->paginate(10);

        return view('posts.index', compact('posts'));
    }

    public function create(): View
    {
        return view('posts.create');
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['title']);

        $post = $request->user()->posts()->create($data);
        Audit::log('post_created', $post);

        return redirect()->route('posts.show', $post)
            ->with('success', 'Publicación creada correctamente.');
    }

    public function show(Post $post): View
    {
        Gate::authorize('view', $post);

        return view('posts.show', compact('post'));
    }

    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $post->update($request->validated());
        Audit::log('post_updated', $post);

        return redirect()->route('posts.show', $post)
            ->with('success', 'Publicación actualizada correctamente.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        Audit::log('post_deleted', $post);
        $post->delete();

        return redirect()->route('posts.index')
            ->with('success', 'Publicación eliminada.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'publicacion';
        $slug = $base;
        $i = 2;

        while (Post::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}