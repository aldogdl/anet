<?php

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicio de validación de tokens JWT administrativos RS256 emitidos por ynksmx_auth.
 *
 * Implementa la compuerta de seguridad Opción B para endpoints de AutoParnet,
 * validando firma criptográfica, claims contractuales y matriz de permisos RBAC.
 */
class AdminJwtService
{
    public const EXPECTED_ALGORITHM = 'RS256';
    public const EXPECTED_ISSUER = 'yonkeros-auth';
    public const EXPECTED_AUDIENCE = 'yonkeros-admin-api';
    public const EXPECTED_SCOPE = 'admin';

    private string $publicKeyPath;
    private ?string $anyToken;
    private int $leewaySeconds;

    /**
     * Matriz canónica de roles y permisos alineada con AdminPermissionMatrix de Auth.
     */
    private const ROLE_PERMISSIONS = [
        'superadmin' => [
            'inventory.read',
            'inventory.write',
            'company.read',
            'company.write',
            'subscription.read',
            'subscription.write',
            'account.block',
            'catalog.read',
            'catalog.write',
        ],
        'support' => [
            'company.read',
            'company.write',
            'subscription.read',
        ],
        'billing' => [
            'company.read',
            'subscription.read',
            'subscription.write',
            'account.block',
        ],
        'catalog' => [
            'catalog.read',
            'catalog.write',
        ],
    ];

    public function __construct(
        string $publicKeyPath,
        ?string $anyToken = null,
        int $leewaySeconds = 60
    ) {
        $this->publicKeyPath = $publicKeyPath;
        $this->anyToken = $anyToken;
        $this->leewaySeconds = $leewaySeconds;
    }

    /**
     * Valida un token JWT en formato string y retorna el contexto de autenticación.
     *
     * @throws AdminAuthException Si el token es inválido, manipulado, expirado o malformado.
     */
    public function verifyToken(string $rawToken): AdminAuthContext
    {
        $parts = explode('.', trim($rawToken));
        if (count($parts) !== 3) {
            throw new AdminAuthException('Estructura de token JWT inválida.', Response::HTTP_UNAUTHORIZED);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // 1. Decodificar encabezado
        $headerJson = $this->base64UrlDecode($encodedHeader);
        $header = json_decode($headerJson, true);
        if (!is_array($header)) {
            throw new AdminAuthException('Encabezado de token JWT malformado.', Response::HTTP_UNAUTHORIZED);
        }

        // Rechazo estricto de cualquier algoritmo distinto de RS256
        if (($header['alg'] ?? '') !== self::EXPECTED_ALGORITHM) {
            throw new AdminAuthException('Algoritmo de firma no permitido.', Response::HTTP_UNAUTHORIZED);
        }

        // 2. Decodificar payload
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new AdminAuthException('Cuerpo de token JWT malformado.', Response::HTTP_UNAUTHORIZED);
        }

        // 3. Decodificar firma
        $signature = $this->base64UrlDecode($encodedSignature);
        if ($signature === false || $signature === '') {
            throw new AdminAuthException('Firma de token JWT inválida.', Response::HTTP_UNAUTHORIZED);
        }

        // 4. Verificación criptográfica con OpenSSL nativo
        $dataToVerify = $encodedHeader . '.' . $encodedPayload;
        $publicKey = $this->loadPublicKey();
        $verifyResult = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verifyResult !== 1) {
            throw new AdminAuthException('Firma criptográfica inválida o clave no reconocida.', Response::HTTP_UNAUTHORIZED);
        }

        // 5. Validación de claims contractuales de Auth
        $now = time();

        // Emisor
        if (($payload['iss'] ?? '') !== self::EXPECTED_ISSUER) {
            throw new AdminAuthException('Emisor de token no autorizado.', Response::HTTP_UNAUTHORIZED);
        }

        // Audiencia
        $aud = $payload['aud'] ?? null;
        $audMatch = false;
        if (is_string($aud) && $aud === self::EXPECTED_AUDIENCE) {
            $audMatch = true;
        } elseif (is_array($aud) && in_array(self::EXPECTED_AUDIENCE, $aud, true)) {
            $audMatch = true;
        }
        if (!$audMatch) {
            throw new AdminAuthException('Audiencia de token no válida para API administrativa.', Response::HTTP_UNAUTHORIZED);
        }

        // Expiración
        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) && !ctype_digit((string)$exp)) {
            throw new AdminAuthException('Claim de expiración ausente o inválido.', Response::HTTP_UNAUTHORIZED);
        }
        if ((int)$exp <= ($now - $this->leewaySeconds)) {
            throw new AdminAuthException('Sesión administrativa expirada.', Response::HTTP_UNAUTHORIZED);
        }

        // Tiempo de emisión (iat)
        $iat = $payload['iat'] ?? null;
        if ($iat !== null && (int)$iat > ($now + $this->leewaySeconds)) {
            throw new AdminAuthException('Token emitido en el futuro.', Response::HTTP_UNAUTHORIZED);
        }

        // Alcance administrativo estricto
        if (($payload['token_scope'] ?? '') !== self::EXPECTED_SCOPE) {
            throw new AdminAuthException('El token no corresponde a una sesión de administración.', Response::HTTP_FORBIDDEN);
        }

        // Sujeto y empresa
        $userId = trim((string)($payload['sub'] ?? ''));
        $companyId = trim((string)($payload['company_id'] ?? ''));
        $roleAdmin = trim((string)($payload['role_admin'] ?? ''));

        if ($userId === '' || $companyId === '' || $roleAdmin === '') {
            throw new AdminAuthException('Claims de identidad administrativa incompletos.', Response::HTTP_FORBIDDEN);
        }

        return new AdminAuthContext(
            $userId,
            $companyId,
            $roleAdmin,
            $payload,
            false
        );
    }

    /**
     * Evalúa si un rol administrativo posee un permiso específico en la matriz RBAC.
     */
    public function hasPermission(string $roleAdmin, string $permission): bool
    {
        $role = strtolower(trim($roleAdmin));
        if ($role === 'superadmin') {
            return true;
        }

        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];
        return in_array($permission, $permissions, true);
    }

    /**
     * Punto de entrada reutilizable para controladores Symfony.
     * Evalúa la cabecera Authorization (Bearer JWT) o el fallback server-to-server (ANY_TOKEN).
     *
     * @throws AdminAuthException Si no está autenticado o carece del permiso requerido.
     */
    public function authenticateRequest(Request $request, string $requiredPermission = 'inventory.read'): AdminAuthContext
    {
        // 1. Verificar fallback server-to-server (ANY_TOKEN)
        $serverToken = $request->headers->get('any-token')
            ?? $request->headers->get('x-admin-token')
            ?? $request->headers->get('admin-token');

        if ($serverToken !== null && trim($serverToken) !== '') {
            if ($this->anyToken !== null && hash_equals($this->anyToken, trim($serverToken))) {
                return new AdminAuthContext(
                    'system-server',
                    'any',
                    'superadmin',
                    ['token_scope' => 'server_admin'],
                    true
                );
            }
        }

        // 2. Extraer Bearer JWT
        $authHeader = $request->headers->get('Authorization') ?? '';
        if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            throw new AdminAuthException('Encabezado de autorización Bearer ausente o inválido.', Response::HTTP_UNAUTHORIZED);
        }

        $context = $this->verifyToken($matches[1]);

        // 3. Evaluar RBAC
        if (!$this->hasPermission($context->getRoleAdmin(), $requiredPermission)) {
            throw new AdminAuthException(
                'Acceso denegado: rol administrativo con facultades insuficientes para esta operación.',
                Response::HTTP_FORBIDDEN
            );
        }

        return $context;
    }

    /**
     * Helper para capturar excepciones y responder con JSON estándar seguro.
     */
    public function handleAuthError(\Throwable $e): JsonResponse
    {
        $status = ($e instanceof AdminAuthException) ? $e->getStatusCode() : Response::HTTP_UNAUTHORIZED;
        $message = ($e instanceof AdminAuthException) ? $e->getMessage() : 'Acceso administrativo denegado.';

        return new JsonResponse([
            'abort' => true,
            'error' => $status === Response::HTTP_FORBIDDEN ? 'forbidden' : 'unauthorized',
            'message' => $message,
        ], $status, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function loadPublicKey(): \OpenSSLAsymmetricKey
    {
        if (!file_exists($this->publicKeyPath)) {
            throw new \RuntimeException('Clave pública de autenticación administrativa no configurada.');
        }

        $pem = file_get_contents($this->publicKeyPath);
        if ($pem === false) {
            throw new \RuntimeException('Error de lectura de clave pública administrativa.');
        }

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new \RuntimeException('Formato de clave pública OpenSSL inválido.');
        }

        return $key;
    }

    private function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
