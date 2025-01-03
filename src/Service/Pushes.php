<?php

namespace App\Service;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\Notification;

class Pushes
{
    private $messaging;
    private $channel = 'RASCHANNEL';

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /** 
     * Metodo para enviar notificaciones push de solicitud de
     * cotizacion a los cotizadores.
     * @param array $token El token del dispositivo a quien se enviará el push.
    */
    public function test(String $token): array
    {
        $title = "Notificación de Prueba";
        $description = "Tu servicio PUSH correcto.";
        $notification = Notification::create(
            $title, $description, 'https://autoparnet.com/ic_launcher.png'
        );
        return $this->send($notification, [$token], ['type' => 'test']);
    }

    /** 
     * Metodo para enviar notificaciones push de solicitud de
     * cotizacion a los cotizadores.
     * @param array $tokens
    */
    public function solicita(array $tokens): array
    {
        $title = "[?] QUIÉN CON...";
        $description = "Facia delantera de un Chevrolet Aveo 2016. ¡AutoparNet trabajando para ti!.";
        $notification = Notification::create(
            $title, $description, 'https://autoparnet.com/ic_launcher.png'
        );
        $data = [
            'item' => 'alkksdñadjasdjñajsdla', 'type' => 'solicita'
        ];

        return $this->send($notification, $tokens, $data);
    }

    /** 
     * Metodo para enviar notificaciones push a los usuarios que
     * esperan una respuesta de cotizacion a una solicitud.
     * @param array $tokens
    */
    public function cotiza(array $tokens)
    {
        $title = "[?] Tendrás estas Refacciones...";
        $description = "Uno de nuestros cientos de clientes nos ha solicitado refacciones. ¡AutoparNet trabajando para ti!.";
        $notification = Notification::create(
            $title, $description, 'https://autoparnet.com/ic_launcher.png'
        );
        $data = [];

        return $this->send($notification, $tokens, $data);
    }

    /** */
    private function send(Notification $notification, array $tokens, array $data): array
    {
        $base = ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'];
        $data = array_merge($base, $data);

        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'notification' => [
                'channel_id' => $this->channel,
            ],
        ]);
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withAndroidConfig($config)
            ->withData($data);

        $rota = count($tokens);
        $result = [
            'cotz' => $rota, 'sended' => 0, 'fails' => 0
        ];
        for ($i=0; $i < $rota; $i++) { 
            $message = $message->toToken($tokens[$i]);
            try {
                $resp = $this->messaging->send($message);
                $result['sended']++;
            } catch (\Throwable $th) {                
                $result['fails']++;
            }
        }
        return $result;
    }

}
