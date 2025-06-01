<?php

namespace App\Service\Any\dto;

/** Mensaje recibido */
class MsgWs
{
    public String $waId  = '';
    public String $name  = '';
    public String $time  = '';
    public String $wamid = '';
    public String $type  = '';
    public String $value = '';
    public array $body = [];

    /** */
    public function __construct(array $msg)
    {
        $msg = $msg['entry'][0]['changes'][0]['value'];
        if(array_key_exists('statuses', $msg)) {
            $this->status($msg['statuses']);
        }else{
            if(array_key_exists('contacts', $msg)) {
                if(array_key_exists('profile', $msg['contacts'])) {
                    $this->name = $msg['contacts']['profile']['name'];
                }
            }
            $this->extraer($msg['messages']);
        }
    }

    /** */
    public function toJson(): String
    {
        return json_encode([
            'waId'  => $this->waId,
            'name'  => $this->name,
            'time'  => $this->time,
            'wamid' => $this->wamid,
            'type'  => $this->type,
            'value' => $this->value,
            'body'  => $this->body,
        ]);        
    }

    /** */
    private function status(array $msg): void
    {
        $this->type = 'stt';
        $this->waId = (array_key_exists('recipient_id', $msg)) ? $msg['recipient_id'] : '';
        $this->wamid= (array_key_exists('id', $msg)) ? $msg['id'] : '';
        $this->time = (array_key_exists('timestamp', $msg)) ? $msg['timestamp'] : '';
        $this->value = (array_key_exists('status', $msg)) ? $msg['status'] : '';
    }

    /** */
    private function extraer(array $msg): void
    {
        $this->type = $msg['type'];
        $this->waId = (array_key_exists('from', $msg)) ? $msg['from'] : '';
        $this->wamid= (array_key_exists('id', $msg)) ? $msg['id'] : '';
        $this->time = (array_key_exists('timestamp', $msg)) ? $msg['timestamp'] : '';
        if($this->type == 'text') {
            $this->value = $msg[$this->type]['body'];
            return;
        }
        $this->scrap($msg[$this->type]);
    }

    /** */
    private function scrap(array $msg): void
    {
        $this->body = $msg;
    }

}
