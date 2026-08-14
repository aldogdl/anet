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
     * Busca el expediente en la carpeta prod_sols, sustituye "_waId_to" y envía cada mensaje a WhatsApp.
     *
     * @param string $expName Nombre del expediente (con o sin extensión .json)
     * @param string $recipientWaId wa_id del usuario que solicitó el expediente (ej. "5213322060352")
     * @param string $prodSolsDir Ruta absoluta a la carpeta prod_sols
     * @param string|null $apiToken Token opcional de WhatsApp API
     * @param string|null $phoneId ID de teléfono opcional de WhatsApp API
     * @return array Resumen del proceso de envío
     */
    public function processAndSend(
        string $expName,
        string $recipientWaId,
        string $prodSolsDir,
        ?string $apiToken = null,
        ?string $phoneId = null
    ): array {
			
        $cleanName = preg_replace('/\.json$/i', '', trim($expName));
        $filePath = rtrim($prodSolsDir, '/\\') . DIRECTORY_SEPARATOR . $cleanName . '.json';

        if (!file_exists($filePath)) {
            return [
                'success'   => false,
                'error'     => "Archivo de expediente no encontrado: {$cleanName}.json en {$prodSolsDir}",
                'total'     => 0,
                'sent'      => 0,
                'failed'    => 0,
                'details'   => [],
            ];
        }

        $rawContent = file_get_contents($filePath);
        $messagesList = json_decode($rawContent, true);

        if (!is_array($messagesList) || empty($messagesList)) {
            return [
                'success'   => false,
                'error'     => "El archivo {$cleanName}.json no contiene una lista de mensajes válida.",
                'total'     => 0,
                'sent'      => 0,
                'failed'    => 0,
                'details'   => [],
            ];
        }

        $totalSent = 0;
        $totalFailed = 0;
        $details = [];

        foreach ($messagesList as $index => $msgItem) {
            if (!is_array($msgItem)) {
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

            $response = $this->waSenderApi->sendRawMessage($msgItem, $apiToken, $phoneId);

            if ($response['success']) {
                $totalSent++;
            } else {
                $totalFailed++;
            }

            $details[] = [
                'index'      => $index,
                'to'         => $msgItem['to'],
                'success'    => $response['success'],
                'status_code'=> $response['status_code'],
                'response'   => $response['response'],
                'error'      => $response['error'],
            ];
        }

        return [
            'success'     => ($totalFailed === 0 && $totalSent > 0),
            'exp_file'    => $cleanName . '.json',
            'recipient'   => $recipientWaId,
            'total'       => count($messagesList),
            'sent'        => $totalSent,
            'failed'      => $totalFailed,
            'details'     => $details,
        ];
    }
}
