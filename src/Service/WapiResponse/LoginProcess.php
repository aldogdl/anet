<?php

namespace App\Service\WapiResponse;

class LoginProcess
{
    public String $hasErr = '';
    public array $toWhatsapp = [];

    /** */
    public function __construct(array $message)
    {
        $timeFin = $this->getTimeKdk($message['timestamp']);
        $this->toWhatsapp = [
            "preview_url" => false,
            "body" => "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana a las " . $timeFin
        ];
    }

    ///
    private function getTimeKdk(String $timestamp): String {

        $date = new \DateTime(strtotime($timestamp));
        return $date->format('h:i:s a');
    }

}