<?php

namespace App\Service;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\AndroidConfig;

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
        $notif = [
            'title' => '[?] Tendrás estas Refacciones...',
            'body' => 'Uno de nuestros cientos de clientes nos ha solicitado refacciones. ¡AutoparNet trabajando para ti!.',
            'channel_id' => $this->channel
        ];

        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => $notif,
            'tag' => $this->channel,
            'notification_priority' => 'PRIORITY_MAX',
            'visibility' => 'PUBLIC'
        ]);
        $notif['image'] = 'https://autoparnet.com/ic_launcher.png';

        $msg = CloudMessage::new()->withNotification($notif)->withAndroidConfig($config);
        
        $res = 'X';
        try {
            $tokensValid = $this->messaging->validateRegistrationTokens($deviceTokens);
            $sendReport = $this->messaging->sendMulticast($msg, $deviceTokens, true);
            $res = 'sended';
        } catch (InvalidMessage $e) {
            $res = $e->errors();
        }

        return [
            'result' => $res,
            'sendReport' => $sendReport,
            'tokensValid'=> $tokensValid
        ];
    }
}