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
     * @param String $token El token del dispositivo a quien se enviará el push.
    */
    public function test(String $token): array
    {
        $notification = Notification::create(
            "Notificación de Prueba",
            "Tu servicio PUSH correcto.",
            'https://autoparnet.com/ic_launcher.png'
        );
        return $this->sendTo($token, $notification, ['type' => 'test']);
    }

    /** 
     * Enviamos los push a multiples contactos
     * @param array $contacts
    */
    public function sendMultiple(array $contacts): array
    {
        $notification = Notification::create(
            $contacts['title'], $contacts['body'],
            'https://autoparnet.com/ic_launcher.png'
        );
        $data = [
            'item' => $contacts['idDbSr'], 'type' => $contacts['type']
        ];
        for ($i=0; $i < $contacts['cant']; $i++) { 
            $result = $this->sendTo($contacts['tokens'][$i], $notification, $data);
        }
        return ['abort' => false, 'msg' => 'Enviado a '.$contacts['cant'].' contactos'];
    }

    /** */
    private function sendTo(String $contact, Notification $notification, array $data): array
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

        $result = ['sended' => [], 'fails' => ''];
        $message = $message->toToken($contact);
        try {
            $resp = $this->messaging->send($message);
            $result['sended'] = $resp;
        } catch (\Throwable $th) {
            $result['fails'] = $th->getMessage();
        }

        file_put_contents('push_sent_sended.json', json_encode($result));
        return $result;
    }

}
