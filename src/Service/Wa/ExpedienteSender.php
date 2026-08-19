<?php

namespace App\Service\Wa;

/**
 * Helper para procesar expedientes de solicitudes (.json) almacenados en prod_sols
 * y enviar cada mensaje individual a la API de WhatsApp Cloud sustituyendo "_waId_to".
 */
class ExpedienteSender
{
    private WaSenderApi $waSenderApi;

    public function __construct(WaSenderApi $waSenderApi)
    {
        $this->waSenderApi = $waSenderApi;
    }

    /**
     * Determina si una respuesta de la API corresponde a un error crítico de autenticación/OAuth.
     */
    public static function isAuthError(array $response): bool
    {
        $statusCode = (int)($response['status_code'] ?? 0);
        if ($statusCode === 401 || $statusCode === 403) {
            return true;
        }

        $resData = $response['response'] ?? [];
        if (is_array($resData) && isset($resData['error'])) {
            $errType = (string)($resData['error']['type'] ?? '');
            $errCode = (int)($resData['error']['code'] ?? 0);
            if ($errType === 'OAuthException' || $errCode === 190) {
                return true;
            }
        }

        $errMsg = (string)($response['error'] ?? '');
        if (stripos($errMsg, 'OAuthException') !== false || stripos($errMsg, 'token') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Registra un log de fallo en la carpeta fb_fails.
     */
    public function logFail(string $fbFailsDir, array $logData, string $prefix = 'exp_fail'): void
    {
        try {
            if (!empty($fbFailsDir)) {
                if (!is_dir($fbFailsDir)) {
                    @mkdir($fbFailsDir, 0777, true);
                }
                $filename = rtrim($fbFailsDir, '/\\') . DIRECTORY_SEPARATOR . $prefix . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.json';
                @file_put_contents($filename, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            // Silenciar errores de escritura de log
        }
    }

    /**
     * Busca el expediente en la carpeta prod_sols (o fallback), sustituye "_waId_to" y envía cada mensaje a WhatsApp.
     * Marca banderas "_sent: true" en mensajes enviados individualmente para evitar duplicidad en reintentos.
     *
     * @param string $expName Nombre del expediente (con o sin extensión .json)
     * @param string $recipientWaId wa_id del usuario que solicitó el expediente (ej. "5213322060352")
     * @param string $prodSolsDir Ruta absoluta a la carpeta prod_sols
     * @param string|null $apiToken Token opcional de WhatsApp API
     * @param string|null $phoneId ID de teléfono opcional de WhatsApp API
     * @param string|null $fbFailsDir Ruta absoluta a la carpeta de logs de fallos fb_fails
     * @param string|null $fallbackDir Ruta alternativa donde buscar el expediente si no está en prodSolsDir
     * @return array Resumen del proceso de envío
     */
    public function processAndSend(
        string $expName,
        string $recipientWaId,
        string $prodSolsDir,
        ?string $apiToken = null,
        ?string $phoneId = null,
        ?string $fbFailsDir = null,
        ?string $fallbackDir = null
    ): array {
        $cleanName = preg_replace('/\.json$/i', '', trim($expName));
        $filePath = rtrim($prodSolsDir, '/\\') . DIRECTORY_SEPARATOR . $cleanName . '.json';

        // Fallback si no existe en prodSolsDir
        if (!file_exists($filePath) && !empty($fallbackDir)) {
            $altPath = rtrim($fallbackDir, '/\\') . DIRECTORY_SEPARATOR . $cleanName . '.json';
            if (file_exists($altPath)) {
                $filePath = $altPath;
            }
        }

        if (!file_exists($filePath)) {
            $errResult = [
                'success'       => false,
                'is_auth_error' => false,
                'error'         => "Archivo de expediente no encontrado: {$cleanName}.json",
                'total'         => 0,
                'sent'          => 0,
                'failed'        => 0,
                'details'       => [],
            ];
            if (!empty($fbFailsDir)) {
                $this->logFail($fbFailsDir, array_merge($errResult, [
                    'recipient' => $recipientWaId,
                    'searched_path' => $filePath,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]), 'exp_not_found');
            }
            return $errResult;
        }

        $rawContent = file_get_contents($filePath);
        $messagesList = json_decode($rawContent, true);

        if (!is_array($messagesList) || empty($messagesList)) {
            $errResult = [
                'success'       => false,
                'is_auth_error' => false,
                'error'         => "El archivo {$cleanName}.json no contiene una lista de mensajes válida.",
                'total'         => 0,
                'sent'          => 0,
                'failed'        => 0,
                'details'       => [],
            ];
            if (!empty($fbFailsDir)) {
                $this->logFail($fbFailsDir, array_merge($errResult, [
                    'recipient' => $recipientWaId,
                    'file' => $filePath,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]), 'exp_invalid_json');
            }
            return $errResult;
        }

        $totalSent = 0;
        $totalFailed = 0;
        $details = [];
        $hasAuthError = false;
        $firstErrorMsg = null;
        $modifiedFlags = false;

        foreach ($messagesList as $index => &$msgItem) {
            if (!is_array($msgItem)) {
                continue;
            }

            // Si este mensaje individual ya había sido enviado exitosamente en una ejecución previa, omitir
            if ((isset($msgItem['_sent']) && $msgItem['_sent'] === true) ||
                (isset($msgItem['sent']) && $msgItem['sent'] === true)) {
                $totalSent++;
                $details[] = [
                    'index'       => $index,
                    'to'          => $msgItem['to'] ?? $recipientWaId,
                    'success'     => true,
                    'status_code' => 200,
                    'already_sent'=> true,
                    'error'       => null,
                ];
                continue;
            }

            // Sustituir "_waId_to" por el destinatario real
            if (isset($msgItem['to']) && $msgItem['to'] === '_waId_to') {
                $msgItem['to'] = $recipientWaId;
            } elseif (empty($msgItem['to'])) {
                $msgItem['to'] = $recipientWaId;
            }

            // Asegurar producto whatsapp
            if (empty($msgItem['messaging_product'])) {
                $msgItem['messaging_product'] = 'whatsapp';
            }

            // Preparar payload limpio para Meta Graph API (sin campos internos de control)
            $cleanPayload = $msgItem;
            unset($cleanPayload['_sent'], $cleanPayload['sent']);

            $response = $this->waSenderApi->sendRawMessage($cleanPayload, $apiToken, $phoneId);

            if ($response['success']) {
                $totalSent++;
                $msgItem['_sent'] = true;
                $modifiedFlags = true;
            } else {
                $totalFailed++;
                if (!$firstErrorMsg && !empty($response['error'])) {
                    $firstErrorMsg = $response['error'];
                }
                if (self::isAuthError($response)) {
                    $hasAuthError = true;
                }
            }

            $details[] = [
                'index'       => $index,
                'to'          => $msgItem['to'],
                'success'     => $response['success'],
                'status_code' => $response['status_code'],
                'response'    => $response['response'],
                'error'       => $response['error'],
            ];
        }
        unset($msgItem); // Romper referencia

        $allDone = ($totalFailed === 0 && $totalSent > 0 && $totalSent === count($messagesList));

        if ($allDone) {
            // Todos los mensajes fueron enviados con éxito: eliminar el archivo del expediente
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        } else {
            // Si hubo fallos parciales o no todos se enviaron, actualizar el archivo JSON con las banderas _sent
            if ($modifiedFlags && file_exists($filePath)) {
                @file_put_contents($filePath, json_encode($messagesList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Registrar log de fallo en fb_fails
            if (!empty($fbFailsDir)) {
                $this->logFail($fbFailsDir, [
                    'success'       => false,
                    'is_auth_error' => $hasAuthError,
                    'exp_file'      => $cleanName . '.json',
                    'filePath'      => $filePath,
                    'recipient'     => $recipientWaId,
                    'total'         => count($messagesList),
                    'sent'          => $totalSent,
                    'failed'        => $totalFailed,
                    'details'       => $details,
                    'error'         => $firstErrorMsg ?? 'Error desconocido al enviar mensajes',
                    'timestamp'     => date('Y-m-d H:i:s'),
                ], 'exp_fail');
            }
        }

        return [
            'success'       => $allDone,
            'is_auth_error' => $hasAuthError,
            'exp_file'      => $cleanName . '.json',
            'deleted'       => !file_exists($filePath),
            'recipient'     => $recipientWaId,
            'total'         => count($messagesList),
            'sent'          => $totalSent,
            'failed'        => $totalFailed,
            'details'       => $details,
            'error'         => $firstErrorMsg,
        ];
    }
}

