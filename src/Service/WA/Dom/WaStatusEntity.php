<?php

namespace App\Service\WA\Dom;

class WaStatusEntity {

    public $field;
    public $wamid;
    public $status;
    public $timestamp;
    public $recipientId;
    public $conversationId;
    public $conversationType;
    public $conversationExpiration;
    public $pricingBillable;
    public $pricingModel;
    public $pricingCategory;
    
    /** */
    public function __construct(array $message)
    {
        $this->field = 'messages';
        $this->wamid = $message['id'];
        $this->status = $message['status'];
        $this->timestamp = $message['timestamp'];
        $this->recipientId = $message['recipient_id'];

        if(array_key_exists('conversation', $message)) {
            $this->conversationId = $message['conversation']['id'];
            $this->conversationType = $message['conversation']['origin']['type'];
            $this->conversationExpiration = 0;
            if( array_key_exists('expiration_timestamp', $message['conversation']) ) {
                $this->conversationExpiration = $message['conversation']['expiration_timestamp'];
            }
        }

        if(array_key_exists('pricing', $message)) {
            $this->pricingBillable = $message['pricing']['billable'];
            $this->pricingModel = $message['pricing']['pricing_model'];
            $this->pricingCategory = $message['pricing']['category'];
        }
    }

    /** */
    public function toArray(): array {

        return [
            'field' => $this->field,
            'wamid' => $this->wamid,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'recipient_id' => $this->recipientId,
            'conversation_id' => $this->conversationId,
            'conversation_type' => $this->conversationType,
            'conversation_expiration' => $this->conversationExpiration,
            'pricing_billable' => $this->pricingBillable,
            'pricing_model' => $this->pricingModel,
            'pricing_category' => $this->pricingCategory,
        ];
    }
}