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
        if (! file_exists($filePath)) {
            $filePath = base_path('docs/seguridad.md');
        }

        $rawMarkdown = file_exists($filePath)
            ? file_get_contents($filePath)
            : "# Documentación Oficial del Sistema - Hospital de Ubaté\n\nConsulte los manuales técnicos disponibles en la carpeta `docs/`.";

        $htmlContent = Str::markdown($rawMarkdown);

        return view('pages.documentation', [
            'htmlContent' => $htmlContent,
        ]);
    }
}
