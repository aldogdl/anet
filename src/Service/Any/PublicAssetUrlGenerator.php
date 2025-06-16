<?php

namespace App\Service\Any;

use Symfony\Component\HttpKernel\KernelInterface;

class PublicAssetUrlGenerator
{
    private string $publicDir;
    private string $baseUrl;

    public function __construct(KernelInterface $kernel)
    {
        $this->publicDir = realpath($kernel->getProjectDir() . '/public_html') ?: '';
        $this->baseUrl = rtrim('https://autoparnet.com');
    }

    /**
     * Convierte un path (absoluto o relativo a public/) en URL pública.
     *
     * @param string $path
     * @return string
     */
    public function generate(string $path): string
    {
        // Si el path es relativo, lo interpretamos respecto a /public
        if (!$this->isAbsolutePath($path)) {
            $path = $this->publicDir . '/' . ltrim($path, '/');
        }

        $realPath = realpath($path);
        if (!$realPath) {
            throw new \RuntimeException("Archivo no encontrado: $path");
        }

        if (strpos($realPath, $this->publicDir) !== 0) {
            throw new \RuntimeException("El archivo no está dentro de la carpeta pública.");
        }

        $relativePath = substr($realPath, strlen($this->publicDir));
        return $this->baseUrl . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }
}
