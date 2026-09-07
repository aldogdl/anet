<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventsWhVPSService
{
    private HttpClientInterface $httpClient;
    private string $urlVPS;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->urlVPS = $params->has('urlWhVPS') ? (string) $params->get('urlWhVPS') : '';
    }

    public function send(string $event, string $action, array $payload): void
    {
        if (empty($this->urlVPS)) {
            return;
        }

        $data = [
            'event'     => $event,
            'action'    => $action,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'payload'   => $payload,
        ];

        try {
            $response = $this->httpClient->request('POST', $this->urlVPS, [
                'json' => $data,
                'timeout' => 1.5,
                'max_duration' => 2.0,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return;
            }
        } catch (\Throwable $th) {
            // Silencioso: fallos de red, timeouts o errores HTTP nunca afectan el flujo principal
        }
    }
}
