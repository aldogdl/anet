<?php

namespace App\Service;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\Notification;

class Pushes
{

    private $messaging;
    private $channel = 'ANETCHANNEL';

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /** */
    public function sendToOwnOfIdType(array $deviceTokens): array
    {
        $title = "[?] Tendrás estas Refacciones...";
        $description = "Uno de nuestros cientos de clientes nos ha solicitado refacciones. ¡AutoparNet trabajando para ti!.";
        $notification = Notification::create($title, $description, 'https://autoparnet.com/ic_launcher.png');
        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'channel_id' => $this->channel,

            ]
        ]);
        $message = CloudMessage::new()->withNotification($notification)->withAndroidConfig($config);
        $resp = $this->messaging->sendMulticast($message, $deviceTokens);
        return [
            'result' => ($resp->count() == count($deviceTokens)) ? 'ok' : 'errs',
            'unknown' => $resp->unknownTokens(),
            'invalid' => $resp->invalidTokens(),
        ];
    }
}