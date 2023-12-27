<?php

namespace App\Service\WapiResponse;

class LoginProcess
{
    public String $hasErr = '';
    public array $toWhatsapp = [];

    /** */
    public function __construct(array $message)
    {
        $cuando = '';
        $timeFin = $this->getTimeKdk($message['timestamp']);
        if($this->hasErr == '') {
            $cuando = " a las " . $timeFin;
        }

        $this->toWhatsapp = [
            "preview_url" => false,
            "body" => "🎟️ Ok, enterados. Te avisamos que tu sesión caducará mañana" . $cuando
        ];
    }

    ///
    private function getTimeKdk(String $timestamp): String {

        try {
            $date = new \DateTime(strtotime($timestamp));
            return $date->format('h:i:s a');
        } catch (\Throwable $th) {
            $this->hasErr = $th->getMessage();
        }
    }

}