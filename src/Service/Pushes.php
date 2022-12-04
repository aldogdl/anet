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

    /**
     * @param $idOrd El Identificador unico de la orden o solicitud de cotizacion
     * @param $avo El Identificador unico del asesor de ventas online
     * @param $camp El Identificador unico de la campaña en cuention
     * La clv pfc es donde se cambiará al id del cotizador, al momento de abrir la app
    */
    public function sendToOwnFinCamp(String $idOrd, String $camp, String $avo, array $tokens): array
    {
        $title = "[?] Tendrás estas Refacciones...";
        $description = "Uno de nuestros cientos de clientes nos ha solicitado refacciones. ¡AutoparNet trabajando para ti!.";
        $notification = Notification::create($title, $description, 'https://autoparnet.com/ic_launcher.png');
        $data = ['screen' => '/cotizo/'.$idOrd.'-pfc-'.$avo.'-'.$camp];
        
        return $this->setCascade($notification, $tokens, $data);
    }

    /** */
    public function sendToOwnOfIdTypeXXX(array $deviceTokens): array
    {
        $title = "[?] Tendrás estas Refacciones...";
        $description = "Uno de nuestros cientos de clientes nos ha solicitado refacciones. ¡AutoparNet trabajando para ti!.";
        $notification = Notification::create($title, $description, 'https://autoparnet.com/ic_launcher.png');

        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'notification' => [
                'channel_id' => $this->channel,
            ],
        ])->withHighPriority();
        // $config = AndroidConfig::fromArray([
        //     'ttl' => '3600s',
        //     'notification' => [
        //         'channel_id' => $this->channel,
        //         'click_action' => 'https://autoparnet.com/cotizo/21-6-2-1667360075598',
        //         'notification_priority' => 'PRIORITY_MAX'
        //     ],
        //     'direct_boot_ok' => true
        // ])->withHighPriority();
        // dd($config);
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withAndroidConfig($config)
            ->withData([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'screen' => '/cotizo/6'
            ]);
        // dd($message);
        $resp = $this->messaging->sendMulticast($message, $deviceTokens);
        return [
            'result' => ($resp->count() == count($deviceTokens)) ? 'ok' : 'errs',
            'unknown' => $resp->unknownTokens(),
            'invalid' => $resp->invalidTokens(),
        ];
    }

    /** */
    private function setCascade(Notification $notification, array $tokens, array $data): array
    {
        $base = ['click_action' => 'FLUTTER_NOTIFICATION_CLICK'];
        $data = array_merge($base, $data);

        $config = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'notification' => [
                'channel_id' => $this->channel,
            ],
        ])->withHighPriority();
        $message = CloudMessage::new()
        ->withNotification($notification)
        ->withAndroidConfig($config)
        ->withData($data);
        
        $tks = [];
        $rota = count($tokens);
        for ($i=0; $i < $rota; $i++) { 
            $tks[] = $tokens[$i]['keyCel'];
        }
        
        $resp = $this->messaging->sendMulticast($message, $tks);
        $result = [
            'result' => ($resp->count() == count($tks)) ? 'ok' : 'errs',
            'unknown' => $resp->unknownTokens(),
            'invalid' => $resp->invalidTokens(),
        ];
        return $this->checkResult($result, $tokens);
    }

    /** */
    private function checkResult(array $result, array $tokens): array
    {   
        $unks = [];
        $invs = [];
        $unk = count($result['unknown']);
        $inv = count($result['invalid']);
        if($unk > 0) {
            for ($i=0; $i < $unk; $i++) { 
                $found = array_search($result['unknown'][$i], array_column($tokens, 'keyCel'));
                if($found !== false) {
                    $unks[] = $tokens[$found]['id'];
                }
            }
        }

        if($inv > 0) {
            for ($i=0; $i < $inv; $i++) { 
                $found = array_search($result['invalid'][$i], array_column($tokens, 'keyCel'));
                if($found !== false) {
                    $invs[] = $tokens[$found]['id'];
                }
            }
        }

        return [
            'result' => $result['result'], 'unknown' => $unks, 'invalid' => $invs,
        ];
    }
}