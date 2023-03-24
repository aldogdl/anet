<?php

namespace App\Service\WA\Dom;

class WaMessageEntity {

    public $contactsName;
    public $contactsPhone;
    public $from;
    public $wamid;
    public $timestamp;
    public $type;
    public $body;

    ///
    public function __construct(array $message) {

        $this->contactsName = $message['contacts'][0]['profile']['name'];
        $this->contactsPhone = $message['contacts'][0]['wa_id'];
        $this->from = $message['messages'][0]['from'];
        $this->wamid = $message['messages'][0]['id'];
        $this->timestamp = $message['messages'][0]['timestamp'];
        $this->type = $message['messages'][0]['type'];
        $this->body = '';
        if(array_key_exists($this->type, $message['messages'][0])) {
            $this->body = $message['messages'][0][$this->type]['body'];
        }
    }

    /** */
    public function toArray():array {

        return [
            'contacts_name' => $this->contactsName,
            'contacts_phone' => $this->contactsPhone,
            'from' => $this->from,
            'wamid' => $this->wamid,
            'timestamp' => $this->timestamp,
            'type' => $this->type,
            'body' => $this->body,
        ];
    }
}