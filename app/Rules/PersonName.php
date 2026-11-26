<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PersonName implements ValidationRule
{
    private int $minLetters;

    private int $maxLength;

    public function __construct(int $minLetters = 2, int $maxLength = 150)
    {
        $this->minLetters = $minLetters;
        $this->maxLength = $maxLength;
    }

    /**
     * Valida que un nombre contenga caracteres válidos para nombres de personas
     * (letras Unicode, acentos/marcas, espacios, apóstrofes, guiones y puntos)
     * y al menos el número configurado de letras reales, rechazando secuencias numéricas,
     * símbolos no permitidos y cadenas exclusivamente de puntuación o espacios.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('El campo :attribute debe ser una cadena de texto.');

            return;
        }

        $trimmed = trim($value);

        if (mb_strlen($trimmed) < $this->minLetters) {
            $fail('El campo :attribute debe contener al menos '.$this->minLetters.' caracteres.');

            return;
        }

        if (mb_strlen($trimmed) > $this->maxLength) {
            $fail('El campo :attribute no puede exceder los '.$this->maxLength.' caracteres.');

            return;
        }

        // Permitir exclusivamente letras Unicode (\p{L}), marcas diacríticas (\p{M}), espacios (\s), apóstrofes (' y ’), guiones (-) y puntos (.)
        if (! preg_match('/^[\p{L}\p{M}\s\'\’\ʼ\-\.]+$/u', $trimmed)) {
            $fail('El campo :attribute sólo puede contener letras, espacios, guiones, puntos o apóstrofes.');

            return;
        }

        // Exigir al menos el mínimo de letras reales (\p{L})
        $letterCount = preg_match_all('/[\p{L}]/u', $trimmed);
        if ($letterCount < $this->minLetters) {
            $fail('El campo :attribute debe contener al menos '.$this->minLetters.' letras.');

            return;
        }

        // Rechazar secuencias no naturales de puntuación repetida
        if (preg_match('/[.\-\'’ʼ]{2,}/u', $trimmed)) {
            $fail('El campo :attribute contiene signos de puntuación no válidos o repetidos.');

            return;
        }
    }
}
