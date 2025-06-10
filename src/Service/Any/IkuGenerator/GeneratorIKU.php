<?php

namespace App\Service\Any\IkuGenerator;

class GeneratorIKU
{
    
    private string $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private int $defaultSize = 12;

    /** */
    public function generate(?int $size = null): string
    {
        $size = $size ?? $this->defaultSize;
        $id = '';
        $max = strlen($this->alphabet) - 1;

        for ($i = 0; $i < $size; $i++) {
            // criptográficamente seguro
            $index = random_int(0, $max);
            $id .= $this->alphabet[$index];
        }

        return $id;
    }
}
