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
    public function sendMultiple(array $push): array
    {
        $thubm = 'https://autoparnet.com/ic_launcher.png';
        if(array_key_exists('thubmnail', $push)) {
            if(mb_strpos($push['thubmnail'], 'autojoya') !== false) {
                $thubm = $push['thubmnail'];
            }
        }
        $notification = Notification::create($push['title'], $push['body'], $thubm);
        $payload = ['idDbSr' => $push['idDbSr'], 'type' => $push['type']];
        if(array_key_exists('srcIdDbSr', $push)) {
            if($push['srcIdDbSr'] > 0) {
                $payload['srcIdDbSr'] = $push['srcIdDbSr'];
            }
        }

        $fails = [];
        for ($i=0; $i < $push['cant']; $i++) { 
            $result = $this->sendTo($push['tokens'][$i], $notification, $payload);
            if(array_key_exists('fails', $result)) {
                $fails[] = $result['fails'];
            }
        }

        $errs = count($fails);
        $msg = 'Enviado a '.($push['cant'] - $errs).' de '.$push['cant'].' contactos';
        $result = ['abort' => false, 'msg' => $msg];
        if($errs > 0) {
            $result['fails'] = $fails;
        }
        return $result;
    }

    /** */
    private function sendTo(String $contact, Notification $notification, array $payload): array
    {
        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'channel_id' => $this->channel,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ]);
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withAndroidConfig($config)
            ->withData($payload);

        $result = [];
        $message = $message->toToken($contact);
        try {
            $resp = $this->messaging->send($message);
            $result['sended'] = $resp;
        } catch (\Throwable $th) {
            $result['fails'] = $th->getMessage();
        }

        return $result;
    }

}
