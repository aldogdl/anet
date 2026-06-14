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

    public function decodeLp(?string $value): string
    {
        if ($value === null || $value === '' || strtolower($value) === 'a') {
            return '';
        }

        if ($this->lpDecodeMap === null) {
            $dicc = $this->fsys->getDiccionary();
            $this->lpDecodeMap = $dicc['lp_decode'] ?? [];
        }

        $valueUpper = strtoupper($value);
        return $this->lpDecodeMap[$valueUpper] ?? $value;
    }
}
