<?php

namespace App\Service;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\Notification;

class Pushes
{
    private Messaging $messaging;
    private String $channel = 'RASCHANNEL';

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }
    
    /** */
    public function subcriptToTopics(array $data): array
    {
        $topics = ['buscar'];
        if(array_key_exists('can_vender', $data)) {
            $topics[] = 'vender';
        }
        $abort  = true;
        $buscar = false;
        $vender = false;
        try {
            $result = $this->messaging->subscribeToTopics($topics, $data['token']);
            if(array_key_exists('buscar', $result)) {
                if($result['buscar'][$data['token']] == 'OK') {
                    $buscar = true;
                }
            }
            if(array_key_exists('vender', $result)) {
                if($result['vender'][$data['token']] == 'OK') {
                    $vender = true;
                }
            }
            $data['subs'] = ['buscar' => $buscar, 'vender' => $vender];
            $abort = false;
        } catch (\Throwable $th) {
            $data['error'] = $th->getMessage();
        }
        return ['abort' => $abort, 'buscar' => $buscar, 'vender' => $vender];
    }

    /** */
    public function isSubscripted(String $token): array
    {
        $appInstance = $this->messaging->getAppInstance($token);
        return $appInstance->rawData();
    }

    /** 
     * Metodo para realizar pruebas de funcionamineto del servicio push
     * @param String $token El token del dispositivo a quien se enviará el push.
    */
    public function test(String $token): array
    {
        $notification = Notification::create(
            "👌 Test Yonkeros",
            "Notificación Web Push Éxitosa",
            'https://autoparnet.com/ic_launcher.png'
        );
        return $this->sendTo($token, $notification, ['type' => 'test']);
    }

    /** 
     * Enviamos un push al contacto que inicio sesion en whatsapp
     * @param array $push
     * @param String $timeInit
    */
    public function sendPushInitLogin(array $push, String $timeInit): void
    {
        $notification = Notification::create(
            '🎟️ Inicio de Sesión',
            'Sistema listo para envio de Oportunidades de Venta',
            'https://autoparnet.com/ic_launcher.png'
        );
        $payload = ['time' => $timeInit, 'type' => 'iniLogin'];
        $rota = count($push);
        for ($i=0; $i < $rota; $i++) { 
            $this->sendTo($push[$i]['token'], $notification, $payload);
        }
    }

    /** 
     * Enviamos un mensaje a un tema
     * @param array $push
    */
    public function sendToTopic(array $push, String $topic): array
    {
        $thubm = 'https://autoparnet.com/ic_launcher.png';
        if(array_key_exists('thubmnail', $push)) {
            $thubm = $push['thubmnail'];
        }

        $notification = Notification::create($push['title'], $push['body'], $thubm);
        $payload = ['idDbSr' => $push['idDbSr'], 'type' => $push['type']];
        if(array_key_exists('srcWaId', $push)) {
            $payload['src'] = $push['device'].'-'.$push['srcWaId'];
        }

        if(array_key_exists('srcIdDbSr', $push)) {
            if($push['srcIdDbSr'] > 0) {
                $payload['srcIdDbSr'] = $push['srcIdDbSr'];
            }
        }

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($payload)
            ->toTopic($topic);

        try {
            $result = $this->messaging->send($message);
        } catch (MessagingException $e) {
            // ...
            $result = ['error' => $e->getMessage()];
        }
        return $result;
    }

    /** 
     * Enviamos los push a multiples contactos
     * @param array $push
    */
    public function sendMultiple(array $push): array
    {
        $thubm = 'https://autoparnet.com/ic_launcher.png';
        if(array_key_exists('thubmnail', $push)) {
            $thubm = $push['thubmnail'];
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
            if($push['tokens'][$i] != '') {
                
                $result = $this->sendTo($push['tokens'][$i], $notification, $payload);
                if(array_key_exists('fails', $result)) {
                    $fails[] = $result['fails'];
                }
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
    public function sendTo(String $contact, Notification $notification, array $payload): array
    {
        $payload['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'channel_id' => $this->channel,
            ],
        ]);
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withAndroidConfig($config)
            ->withHighestPossiblePriority()
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
