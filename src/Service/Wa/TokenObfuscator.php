<?php

namespace App\Service\Wa;

/**
 * Helper para ofuscar/dividir el token de WhatsApp en 3 partes seguras 
 * antes de enviarlo al cliente.
 */
class TokenObfuscator
{
    /**
     * Divide un token de WhatsApp en 3 partes y devuelve la estructura JSON requerida.
     * 
     * id    => primera parte (texto plano)
     * token => segunda parte (base64_encode)
     * lock  => tercera parte + '|tkend'
     *
     * @param string $rawToken
     * @return array
     */
    public static function obfuscate(string $rawToken): array
    {
        $token = trim($rawToken);
        $len = strlen($token);

        if ($len < 3) {
            return [
                'id'    => $token,
                'token' => base64_encode(''),
                'lock'  => '|tkend',
            ];
        }

        $partLen = (int) floor($len / 3);

        $part1 = substr($token, 0, $partLen);
        $part2 = substr($token, $partLen, $partLen);
        $part3 = substr($token, $partLen * 2);

        return [
            'id'    => $part1,
            'token' => base64_encode($part2),
            'lock'  => $part3 . '|tkend',
        ];
    }

    /**
     * Reconstruye el token original a partir de la estructura ofuscada.
     *
     * @param array $obfuscated
     * @return string
     */
    public static function deobfuscate(array $obfuscated): string
    {
        $part1 = (string) ($obfuscated['id'] ?? '');
        $part2Raw = (string) ($obfuscated['token'] ?? '');
        $part2 = base64_decode($part2Raw);
        $lockRaw = (string) ($obfuscated['lock'] ?? '');
        $part3 = str_replace('|tkend', '', $lockRaw);

        return $part1 . $part2 . $part3;
    }
}
