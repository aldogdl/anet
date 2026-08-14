<?php

namespace App\Service\Wa;

/**
 * Helper de alto rendimiento para extraer y estructurar de manera rápida y eficiente 
 * la información clave de los payloads provenientes de webhooks de WhatsApp Cloud API.
 */
class PayloadExtractor
{
    /**
     * Extrae un arreglo estructurado con la información clave del payload de WhatsApp.
     *
     * @param array|string $payload Array decodificado de JSON o string con el JSON crudo.
     * @return array Estructura limpia y estandarizada.
     */
    public static function extract(array|string $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $result = [
            'is_valid'             => false,
            'is_status'            => false,
            'event_type'           => 'unknown', // 'text', 'image', 'document', 'audio', 'video', 'location', 'interactive', 'status'
            'entry_id'             => '',
            'phone_number_id'      => '',
            'display_phone_number' => '',
            'contact_name'         => '',
            'wa_id'                => '',
            'user_id'              => '',
            'msg_id'               => '',
            'from'                 => '',
            'timestamp'            => 0,
            'body'                 => '',
            'exp_file'             => null, // Si contiene etiqueta [exp]<filename>
            'media'                => null, // Si contiene imagen/documento/video/audio
            'status_info'          => null, // Si es actualización de estado
            'raw_message'          => null,
        ];

        if (empty($payload) || !isset($payload['entry'][0]['changes'][0]['value'])) {
            return $result;
        }

        $entry = $payload['entry'][0];
        $result['entry_id'] = (string)($entry['id'] ?? '');

        $value = $entry['changes'][0]['value'];

        // Metadata de la cuenta de WhatsApp Business
        if (isset($value['metadata'])) {
            $result['display_phone_number'] = (string)($value['metadata']['display_phone_number'] ?? '');
            $result['phone_number_id']      = (string)($value['metadata']['phone_number_id'] ?? '');
        }

        // Perfil del contacto remitente
        if (isset($value['contacts'][0])) {
            $contact = $value['contacts'][0];
            $result['contact_name'] = (string)($contact['profile']['name'] ?? '');
            $result['wa_id']        = (string)($contact['wa_id'] ?? '');
            $result['user_id']       = (string)($contact['user_id'] ?? '');
        }

        // Caso 1: Notificación de estado (sent, delivered, read, failed)
        if (isset($value['statuses'][0])) {
            $stt = $value['statuses'][0];
            $result['is_valid']    = true;
            $result['is_status']   = true;
            $result['event_type']  = 'status';
            $result['msg_id']      = (string)($stt['id'] ?? '');
            $result['from']        = (string)($stt['recipient_id'] ?? '');
            $result['timestamp']   = (int)($stt['timestamp'] ?? 0);
            $result['status_info'] = [
                'status'    => $stt['status'] ?? '',
                'recipient' => $stt['recipient_id'] ?? '',
                'pricing'   => $stt['pricing'] ?? null,
                'errors'    => $stt['errors'] ?? null,
            ];
            return $result;
        }

        // Caso 2: Mensaje entrante (texto, imagen, documento, etc.)
        if (isset($value['messages'][0])) {
            $msg = $value['messages'][0];
            $result['raw_message'] = $msg;
            $result['is_valid']    = true;
            $result['msg_id']      = (string)($msg['id'] ?? '');
            $result['from']        = (string)($msg['from'] ?? '');
            $result['user_id']     = (string)($msg['from_user_id'] ?? $result['user_id']);
            $result['timestamp']   = (int)($msg['timestamp'] ?? 0);
            $type                  = (string)($msg['type'] ?? 'unknown');
            $result['event_type']  = $type;

            if (empty($result['wa_id'])) {
                $result['wa_id'] = $result['from'];
            }

            switch ($type) {
                case 'text':
                    $result['body'] = (string)($msg['text']['body'] ?? '');
                    break;

                case 'image':
                    $img = $msg['image'] ?? [];
                    $result['body']  = (string)($img['caption'] ?? '');
                    $result['media'] = [
                        'type'      => 'image',
                        'id'        => (string)($img['id'] ?? ''),
                        'url'       => (string)($img['url'] ?? ''),
                        'mime_type' => (string)($img['mime_type'] ?? ''),
                        'sha256'    => (string)($img['sha256'] ?? ''),
                        'caption'   => (string)($img['caption'] ?? ''),
                    ];
                    break;

                case 'document':
                    $doc = $msg['document'] ?? [];
                    $result['body']  = (string)($doc['caption'] ?? '');
                    $result['media'] = [
                        'type'      => 'document',
                        'id'        => (string)($doc['id'] ?? ''),
                        'url'       => (string)($doc['url'] ?? ''),
                        'mime_type' => (string)($doc['mime_type'] ?? ''),
                        'filename'  => (string)($doc['filename'] ?? ''),
                        'caption'   => (string)($doc['caption'] ?? ''),
                    ];
                    break;

                case 'audio':
                case 'voice':
                    $aud = $msg[$type] ?? [];
                    $result['media'] = [
                        'type'      => $type,
                        'id'        => (string)($aud['id'] ?? ''),
                        'url'       => (string)($aud['url'] ?? ''),
                        'mime_type' => (string)($aud['mime_type'] ?? ''),
                    ];
                    break;

                case 'video':
                    $vid = $msg['video'] ?? [];
                    $result['body']  = (string)($vid['caption'] ?? '');
                    $result['media'] = [
                        'type'      => 'video',
                        'id'        => (string)($vid['id'] ?? ''),
                        'url'       => (string)($vid['url'] ?? ''),
                        'mime_type' => (string)($vid['mime_type'] ?? ''),
                        'caption'   => (string)($vid['caption'] ?? ''),
                    ];
                    break;

                case 'interactive':
                    $inter = $msg['interactive'] ?? [];
                    $interType = $inter['type'] ?? '';
                    if ($interType === 'button_reply' && isset($inter['button_reply'])) {
                        $result['body'] = (string)($inter['button_reply']['title'] ?? '');
                        $result['media'] = [
                            'type' => 'button_reply',
                            'id'   => (string)($inter['button_reply']['id'] ?? ''),
                        ];
                    } elseif ($interType === 'list_reply' && isset($inter['list_reply'])) {
                        $result['body'] = (string)($inter['list_reply']['title'] ?? '');
                        $result['media'] = [
                            'type'        => 'list_reply',
                            'id'          => (string)($inter['list_reply']['id'] ?? ''),
                            'description' => (string)($inter['list_reply']['description'] ?? ''),
                        ];
                    }
                    break;

                case 'location':
                    $loc = $msg['location'] ?? [];
                    $result['body']  = (string)($loc['name'] ?? $loc['address'] ?? '');
                    $result['media'] = [
                        'type'      => 'location',
                        'latitude'  => $loc['latitude'] ?? null,
                        'longitude' => $loc['longitude'] ?? null,
                        'name'      => (string)($loc['name'] ?? ''),
                        'address'   => (string)($loc['address'] ?? ''),
                    ];
                    break;

                case 'button':
                    $btn = $msg['button'] ?? [];
                    $result['body'] = (string)($btn['text'] ?? $btn['payload'] ?? '');
                    break;
            }

            // Detección automática del parámetro [exp]<filename> en el texto del mensaje
            if (!empty($result['body'])) {
                if (preg_match('/\[exp\]([a-zA-Z0-9_\-\.]+)/i', $result['body'], $matches)) {
                    $result['exp_file'] = trim($matches[1]).'.json';
                }
            }
        }

        return $result;
    }
}
