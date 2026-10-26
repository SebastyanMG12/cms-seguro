<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function documentation(): View
    {
        $filePath = base_path('INFORME_IMPLEMENTACION_Y_SEGURIDAD.md');
        $rawMarkdown = file_exists($filePath) ? file_get_contents($filePath) : '# Documentación no disponible';
        $htmlContent = Str::markdown($rawMarkdown);

        return view('pages.documentation', [
            'htmlContent' => $htmlContent,
        ]);
    }
}
