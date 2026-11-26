<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

class PublicNewsController extends Controller
{
    /**
     * Muestra el portal público de noticias y avisos institucionales del Hospital de Ubaté.
     * Únicamente expone artículos en estado 'published'.
     */
    public function index(): View
    {
        $news = Post::with('user:id,name')
            ->where('status', 'published')
            ->latest('created_at')
            ->paginate(9);

        return view('news.index', compact('news'));
    }

    /**
     * Muestra el detalle público de un artículo informativo institucional por su slug.
     * Exige estado 'published' y escapa todo contenido en la vista para prevenir XSS.
     */
    public function show(string $slug): View
    {
        $post = Post::with('user:id,name')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('news.show', compact('post'));
    }
}
