<?php

namespace App\Service\WA;

class BuildPayloadMsg {

    private $tipos = [
        'text' => 'text',
        'image' => 'image',
        'interactive' => 'interactive',
        'text' => 'text',
    ];

    private $messageProduct = "whatsapp";
    private $recipientType = "individual";
    private $to;

    /** */
    private function getBasicBody() {
        return [
            "messaging_product" => $this->messageProduct,
            "recipient_type" => $this->recipientType,
            "to" => $this->to,
        ];
    }

    /**
     * MESSAGE_ID
    */
    public function bodyReader(String $msgID) {
        return [
            "messaging_product" => "whatsapp",
            "status" => "read",
            "message_id" => $msgID
        ];
    }

    /** */
    public function bodyText() {

        $body = $this->getBasicBody();
        $body['type'] = $this->tipos['text'];
        $body['text'] = [
            "preview_url" => false,
            "body" => "MESSAGE_CONTENT"
        ];
        return $body;
    }

    /** */
    public function bodyMedia() {

    }

    /** */
    public function bodyInteractive() {

    }
}