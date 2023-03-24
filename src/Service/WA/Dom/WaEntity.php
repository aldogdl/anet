<?php

namespace App\Service\WA\Dom;

use App\Service\WA\Dom\WaAcountEntity;
use App\Service\WA\Dom\WaStatusEntity;
use App\Service\WA\Dom\WaMessageEntity;

class WaEntity {

    public WaAcountEntity $acount;
    public ?WaStatusEntity $status = NULL;
    public ?WaMessageEntity $message = NULL;
    public String $type = '';
    public String $value = '';

    /** */
    public function __construct(array $message)
    {
        $this->acount = new WaAcountEntity($message);
        if(array_key_exists('statuses', $message['entry'][0]['changes'][0]['value'])) {
            $this->status = new WaStatusEntity(
                $message['entry'][0]['changes'][0]['value']['statuses'][0]
            );
            $this->type = 'status';
            $this->value = $this->status->status;
        }
        if(array_key_exists('messages', $message['entry'][0]['changes'][0]['value'])) {
            $this->message = new WaMessageEntity(
                $message['entry'][0]['changes'][0]['value']
            );
            $this->type = 'message';
            $this->value = $this->message->type;
        }
    }

    /** */
    public function toArray(): array {

        return [
            'type'   => $this->type,
            'value'  => $this->value,
            'acount' => $this->acount->toArray(),
            'status' => (!is_null($this->status)) ? $this->status->toArray() : [],
            'message'=> (!is_null($this->message)) ? $this->message->toArray() : [],
        ];
    }
}