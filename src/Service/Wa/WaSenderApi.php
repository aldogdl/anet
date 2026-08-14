<?php

namespace App\Service\Wa;

/**
 * Helper para realizar envíos de mensajes a la API de WhatsApp Cloud (Graph API de Meta).
 */
class WaSenderApi
{
    private string $phoneNumberId;
    private string $apiToken;
    private string $apiVersion;

    public function __construct(
        string $phoneNumberId = '109783945332013',
        string $apiToken = '',
        string $apiVersion = 'v25.0'
    ) {
        $this->phoneNumberId = $phoneNumberId;
        $this->apiToken = $apiToken;
        $this->apiVersion = $apiVersion;
    }

    /**
     * Resuelve el token de WhatsApp a utilizar, buscando en el parámetro explícito,
     * en la propiedad de la clase o en el archivo wtst.txt de la raíz del proyecto.
     */
    public function resolveToken(?string $overrideToken = null): string
    {
        if (!empty($overrideToken)) {
            $token = trim($overrideToken);
            if (!str_starts_with($token, '#')) {
                return $token;
            }
        }

        if (!empty($this->apiToken)) {
            $token = trim($this->apiToken);
            if (!str_starts_with($token, '#')) {
                return $token;
            }
        }

        $candidates = [
            'public_html/wtst.txt',
            dirname(__DIR__, 3) . '/public_html/wtst.txt',
            'wtst.txt',
            dirname(__DIR__, 3) . '/wtst.txt',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (!empty($trimmed) && !str_starts_with($trimmed, '#')) {
                            return $trimmed;
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * Envía una estructura de mensaje cruda (JSON Array) a la API de WhatsApp Cloud.
     *
     * @param array $payload Estructura completa del mensaje.
     * @param string|null $overrideToken Token opcional para sobrescribir el token por defecto.
     * @param string|null $overridePhoneId ID del número de teléfono opcional.
     * @return array Respuesta conteniendo 'success', 'status_code', 'response' y 'error'.
     */
    public function sendRawMessage(
        array $payload,
        ?string $overrideToken = null,
        ?string $overridePhoneId = null
    ): array {
        $phoneId = !empty($overridePhoneId) ? $overridePhoneId : $this->phoneNumberId;
        $token   = $this->resolveToken($overrideToken);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$phoneId}/messages";

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=UTF-8',
            ],
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $responseRaw = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        $decodedResponse = json_decode($responseRaw, true);

        return [
            'success'     => ($httpCode >= 200 && $httpCode < 300),
            'status_code' => $httpCode,
            'response'    => $decodedResponse ?? $responseRaw,
            'error'       => $curlError ?: ($httpCode >= 400 ? ($decodedResponse['error']['message'] ?? 'HTTP Error ' . $httpCode) : null),
        ];
    }

    /**
     * Envía un mensaje de texto individual.
     */
    public function sendTextMessage(
        string $toWaId,
        string $textBody,
        ?string $overrideToken = null,
        ?string $overridePhoneId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toWaId,
            'type'              => 'text',
            'text'              => [
                'body' => $textBody,
            ],
        ];

        return $this->sendRawMessage($payload, $overrideToken, $overridePhoneId);
    }

    /**
     * Ejemplo placeholder de envío tipo plantilla (template).
     */
    public function sendTemplateMessage(
        string $toWaId,
        string $templateName = 'jaspers_market_plain_text_v1',
        string $languageCode = 'en_US',
        ?string $overrideToken = null,
        ?string $overridePhoneId = null
    ): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $toWaId,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        return $this->sendRawMessage($payload, $overrideToken, $overridePhoneId);
    }
}
