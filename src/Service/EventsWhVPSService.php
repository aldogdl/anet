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
        file_put_contents(
            'anet_wh_debug.log',
            date('c') . " START event=$event action=$action url={$this->urlVPS}\n",
            FILE_APPEND
        );

        if (empty($this->urlVPS)) {
            file_put_contents(
                'anet_wh_debug.log',
                date('c') . " ABORT urlVPS VACIA\n",
                FILE_APPEND
            );
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
                'timeout' => 10,
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
