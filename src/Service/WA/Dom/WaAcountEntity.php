<?php

namespace App\Service\WA\Dom;

class WaAcountEntity {

    public $id;
    public $object;
    public $displayPhoneNumber;
    public $phoneNumberId;
    public $messagingProduct;

    public function __construct(array $message)
    {
        $msgValue = $message['entry'][0]['changes'][0]['value'];
        $this->id = $message['entry'][0]['id'];
        $this->object = $message['object'];
        $this->messagingProduct = $msgValue['messaging_product'];
        $this->displayPhoneNumber = $msgValue['metadata']['display_phone_number'];
        $this->phoneNumberId = $msgValue['metadata']['phone_number_id'];
    }

    /** */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'display_phone_number' => $this->displayPhoneNumber,
            'phone_number_id' => $this->phoneNumberId,
            'messaging_product' => $this->messagingProduct,
        ];
    }
}
