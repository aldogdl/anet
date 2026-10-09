<?php

namespace App\Service\Any\MeLi;

use App\Service\DataSimpleMlm;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Servicio encargado de la validación criptográfica, consumo de nonces,
 * intercambio de códigos y notificación S2S del flujo OAuth de Mercado Libre.
 */
class MeliOAuthService
{
    private string $secret;
    private string $notifyUrl;
    private DataSimpleMlm $dataSimpleMlm;
    private HttpClientInterface $httpClient;

    public function __construct(
        ParameterBagInterface $params,
        DataSimpleMlm $dataSimpleMlm,
        HttpClientInterface $httpClient
    ) {
        $this->secret = $params->has('meliBootstrapSecret') ? (string) $params->get('meliBootstrapSecret') : '';
        $rawVpsUrl = $params->has('urlWhVPS') ? (string) $params->get('urlWhVPS') : '';
        $derivedUrl = preg_replace('#/anet/wh/?$#', '/meli/internal/linked-notify', $rawVpsUrl);
        $this->notifyUrl = (!empty($derivedUrl) && $derivedUrl !== $rawVpsUrl)
            ? $derivedUrl
            : 'https://yonkerosmx.com/meli/internal/linked-notify';

        $this->dataSimpleMlm = $dataSimpleMlm;
        $this->httpClient = $httpClient;
    }

    /**
     * Valida la autenticidad, integridad y vigencia del parámetro state.
     *
     * @param string $state Formato esperado: <payloadBase64Url>.<signatureBase64Url>
     * @return array{valid: bool, slug: ?string, nonce: ?string, error: ?string, statusCode: int}
     */
    public function validateState(string $state): array
    {
        $cleanState = trim($state);
        if ($cleanState === '' || !str_contains($cleanState, '.')) {
            return [
                'valid' => false,
                'slug' => null,
                'nonce' => null,
                'error' => 'El parámetro de seguridad (state) no tiene un formato válido.',
                'statusCode' => 400,
            ];
        }

        [$payloadB64, $signatureB64] = explode('.', $cleanState, 2);

        // 1. Validar firma HMAC-SHA256
        $rawCalculatedSig = base64_encode(hash_hmac('sha256', $payloadB64, $this->secret, true));
        $calculatedSig = rtrim(strtr($rawCalculatedSig, '+/', '-_'), '=');
        $cleanSignature = rtrim($signatureB64, '=');

        if (!hash_equals($calculatedSig, $cleanSignature)) {
            return [
                'valid' => false,
                'slug' => null,
                'nonce' => null,
                'error' => 'Firma de seguridad inválida o manipulada (403 Forbidden).',
                'statusCode' => 403,
            ];
        }

        // 2. Decodificar payload
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
        $stateData = json_decode((string) $payloadJson, true);

        if (!is_array($stateData) || empty($stateData['slug']) || empty($stateData['nonce'])) {
            return [
                'valid' => false,
                'slug' => null,
                'nonce' => null,
                'error' => 'El contenido de la sesión de vinculación es inválido.',
                'statusCode' => 400,
            ];
        }

        $slug = trim((string) $stateData['slug']);
        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
        $exp = (int) ($stateData['exp'] ?? 0);
        $nonce = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $stateData['nonce']);

        if ($safeSlug === '' || $nonce === '') {
            return [
                'valid' => false,
                'slug' => null,
                'nonce' => null,
                'error' => 'Identificador de empresa o nonce inválido.',
                'statusCode' => 400,
            ];
        }

        // 3. Validar TTL / Expiración (10 min)
        if ($exp < time()) {
            return [
                'valid' => false,
                'slug' => null,
                'nonce' => null,
                'error' => 'El enlace de vinculación ha expirado (TTL 10 min). Por favor inicie nuevamente desde la app.',
                'statusCode' => 400,
            ];
        }

        return [
            'valid' => true,
            'slug' => $safeSlug,
            'nonce' => $nonce,
            'error' => null,
            'statusCode' => 200,
        ];
    }

    /**
     * Consume de forma atómica el nonce para prevenir ataques de replay.
     *
     * @param string $nonce Identificador único de 32 hex chars
     * @return bool True si es la primera vez que se consume; false si ya existía
     */
    public function consumeNonce(string $nonce): bool
    {
        $safeNonce = preg_replace('/[^a-zA-Z0-9_\-]/', '', $nonce);
        if ($safeNonce === '') {
            return false;
        }

        $nonceDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'meli_nonces';
        if (!is_dir($nonceDir)) {
            @mkdir($nonceDir, 0777, true);
        }

        $nonceFile = $nonceDir . DIRECTORY_SEPARATOR . 'nonce_' . $safeNonce . '.lock';
        $handle = @fopen($nonceFile, 'x');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) time());
        fclose($handle);
        return true;
    }

    /**
     * Coordina el canje del código de autorización con Mercado Libre, la persistencia
     * en el expediente de la empresa y la notificación S2S al VPS de autenticación.
     *
     * @param string $code Código temporal emitido por MeLi
     * @param string $slug Identificador sanitizado de la empresa
     * @return array{success: bool, slug: ?string, userId: ?int, error: ?string, statusCode: int}
     */
    public function exchangeAndNotify(string $code, string $slug): array
    {
        // 1. Canje con MeLi y persistencia en dtaCtcLog/{slug}.json
        $res = $this->dataSimpleMlm->parseCodeToToken($code, $slug);

        if (isset($res['abort']) && $res['abort'] === true) {
            $errMsg = $res['body']['error'] ?? $res['error'] ?? 'Error desconocido al procesar credenciales';
            return [
                'success' => false,
                'slug' => null,
                'userId' => null,
                'error' => 'Error al canjear credenciales con Mercado Libre: ' . htmlspecialchars((string) $errMsg),
                'statusCode' => 502,
            ];
        }

        if (empty($res['token']) || empty($res['userId'])) {
            return [
                'success' => false,
                'slug' => null,
                'userId' => null,
                'error' => 'Mercado Libre no retornó credenciales válidas.',
                'statusCode' => 502,
            ];
        }

        $userId = (int) $res['userId'];

        // 2. Notificación S2S hacia ynksmx_auth (POST /meli/internal/linked-notify)
        $notifyResult = $this->notifyVps($slug, $userId);
        if (!$notifyResult['success']) {
            return [
                'success' => false,
                'slug' => $slug,
                'userId' => $userId,
                'error' => 'Las credenciales fueron obtenidas, pero falló la incorporación al servidor central de autenticación (' . htmlspecialchars($notifyResult['error'] ?? '') . '). Por favor intente vincular nuevamente desde la app.',
                'statusCode' => 502,
            ];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'userId' => $userId,
            'error' => null,
            'statusCode' => 200,
        ];
    }

    /**
     * Envía la notificación S2S al VPS.
     *
     * @param string $slug
     * @param int $userId
     * @return array{success: bool, error: ?string}
     */
    private function notifyVps(string $slug, int $userId): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->notifyUrl, [
                'headers' => [
                    'X-Internal-Secret' => $this->secret,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'slug' => $slug,
                    'userId' => $userId,
                    'timestamp' => time(),
                ],
                'timeout' => 5,
                'max_duration' => 8,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return ['success' => true, 'error' => null];
            }

            return ['success' => false, 'error' => 'El servidor central respondió con código HTTP ' . $statusCode];
        } catch (\Throwable $th) {
            return ['success' => false, 'error' => $th->getMessage()];
        }
    }
}
