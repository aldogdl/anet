<?php

namespace App\Twig;

use App\Service\Any\Fsys\Fsys;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private Fsys $fsys;
    private ?array $lpDecodeMap = null;

    public function __construct(Fsys $fsys)
    {
        $this->fsys = $fsys;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('lp_decode', [$this, 'decodeLp']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('lp_decode', [$this, 'decodeLp']),
        ];
    }

    public function decodeLp(?string $value, ?string $pieza = null): string
    {
        if ($value === null || $value === '' || strtolower($value) === 'a') {
            return '';
        }

        if ($this->lpDecodeMap === null) {
            $dicc = $this->fsys->getDiccionary();
            $this->lpDecodeMap = $dicc['lp_decode'] ?? [];
        }

        $valueUpper = strtoupper($value);
        $decoded = $this->lpDecodeMap[$valueUpper] ?? $value;

        // Raíces del diccionario que cambian de género (Izquierd, Derech, Delanter, Traser)
        $genderRoots = ['Izquierd', 'Derech', 'Delanter', 'Traser'];
        if (in_array($decoded, $genderRoots)) {
            $suffix = $this->getGenderSuffix($pieza);
            $decoded .= $suffix;
        }

        return $decoded;
    }

    private function getGenderSuffix(?string $pieza): string
    {
        if ($pieza === null || trim($pieza) === '') {
            return 'o'; // Por defecto masculino
        }

        // Limpiar espacios múltiples y separar palabras
        $pieza = trim(preg_replace('/\s+/', ' ', $pieza));
        $words = explode(' ', $pieza);
        
        $vowels = ['a', 'e', 'i', 'o', 'u', 'á', 'é', 'í', 'ó', 'ú'];
        $targetVowel = 'o'; // Por defecto masculino

        if (count($words) >= 2) {
            // Regla a): más de una palabra. Evaluamos la primera.
            $firstWord = $words[0];
            $lastCharFirst = mb_substr(mb_strtolower($firstWord), -1);
            if (in_array($lastCharFirst, $vowels)) {
                $targetVowel = $lastCharFirst;
            } else {
                // Si la primera termina en consonante, evaluamos la segunda.
                $secondWord = $words[1];
                $lastCharSecond = mb_substr(mb_strtolower($secondWord), -1);
                if (in_array($lastCharSecond, $vowels)) {
                    $targetVowel = $lastCharSecond;
                }
            }
        } else if (count($words) === 1) {
            // Regla b): una sola palabra.
            $word = $words[0];
            $lastChar = mb_substr(mb_strtolower($word), -1);
            if (in_array($lastChar, $vowels)) {
                $targetVowel = $lastChar;
            }
        }

        // Si la vocal identificada es 'a' o 'á' (femenino), devolvemos 'a'.
        // De lo contrario (o, e, i, u, consonantes), devolvemos 'o' (masculino).
        if ($targetVowel === 'a' || $targetVowel === 'á') {
            return 'a';
        }

        return 'o';
    }
}
