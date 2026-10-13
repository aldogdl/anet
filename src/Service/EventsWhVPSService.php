<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventsWhVPSService
{
    private HttpClientInterface $httpClient;
    private string $urlVPS;
    private string $secret;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->urlVPS = $params->has('urlWhVPS') ? trim((string) $params->get('urlWhVPS')) : '';
        
        $secret = $params->has('anetWebhookSecret') ? (string) $params->get('anetWebhookSecret') : '';
        if (empty($secret) && isset($_ENV['ANET_WEBHOOK_SECRET'])) {
            $secret = (string) $_ENV['ANET_WEBHOOK_SECRET'];
        }
        if (empty($secret) && getenv('ANET_WEBHOOK_SECRET') !== false) {
            $secret = (string) getenv('ANET_WEBHOOK_SECRET');
        }
        $this->secret = trim($secret);
    }

    public function send(string $event, string $action, array $payload): void
    {
        // Validación estricta: nunca enviar webhooks sin URL o sin autenticación
        if (empty($this->urlVPS) || empty($this->secret)) {
            $msg = 'Configuración obligatoria de webhook VPS incompleta (urlWhVPS o anetWebhookSecret no configurados). Webhook abortado.';
            file_put_contents(
                'anet_wh_debug.log',
                date('c') . " ABORT: $msg\n",
                FILE_APPEND
            );
            throw new \RuntimeException($msg);
        }

        // Registro de auditoría seguro (sin exponer secretos ni tokens)
        file_put_contents(
            'anet_wh_debug.log',
            date('c') . " START event=$event action=$action url={$this->urlVPS}\n",
            FILE_APPEND
        );

        $data = [
            'event'     => $event,
            'action'    => $action,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'payload'   => $payload,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->urlVPS, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secret,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json'         => $data,
                'timeout'      => 10,
                'max_duration' => 15,
            ]);

            $status = $response->getStatusCode();
            file_put_contents(
                'anet_wh_debug.log',
                date('c') . " RESPONSE status=$status\n",
                FILE_APPEND
            );
            if ($status < 200 || $status >= 300) {
                return;
            }
        } catch (\Throwable $th) {
            file_put_contents(
                'anet_wh_debug.log',
                date('c') . ' ERROR ' . $th->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}
