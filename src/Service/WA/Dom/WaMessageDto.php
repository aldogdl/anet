<?php

namespace App\Service\WA\Dom;

class WaMessageDto {

    public String $id = '0';
    public String $context = '0';
    public String $waId = '';
    public String $body = '';
    public String $type = '';
    public String $campoResponsed = '';
    public String $expira = '';
    public String $timeStamp = '';
    public bool $canResponse = false;
    
    /** */
    public function __construct(array $message)
    {
        $mapValue = $message['entry'][0]['changes'][0]['value'];

        if(array_key_exists('statuses', $mapValue)) {
            $this->extractStatus($mapValue);
            return;
        }
        
        if(array_key_exists('messages', $mapValue)) {
            $this->extractMessage($mapValue);
        }
    }

    /** */
    private function extractStatus(array $mapValue) : void
    {
        $this->type = 'status';
        $this->body = $mapValue['statuses'][0]['status'];
        $this->timeStamp = $mapValue['statuses'][0]['timestamp'];
        $this->extractWaId($mapValue['statuses'][0]['recipient_id']);

        if(array_key_exists('conversation', $mapValue['statuses'][0])) {
            if(array_key_exists('expiration_timestamp', $mapValue['statuses'][0]['conversation'])) {
                $this->expira = $mapValue['statuses'][0]['conversation']['expiration_timestamp'];
            }
            if(array_key_exists('origin', $mapValue['statuses'][0]['conversation'])) {
                $type = $mapValue['statuses'][0]['conversation']['origin']['type'];
                if($type == 'service') {
                    $this->canResponse = true; 
                }
            }
        }
    }

    /** */
    private function extractMessage(array $mapValue) : void
    {
        
        $this->id   = $mapValue['messages'][0]['id'];
        $this->timeStamp = $mapValue['messages'][0]['timestamp'];
        $this->extractWaId($mapValue['messages'][0]['from']);
        
        $typeBody = $mapValue['messages'][0]['type'];
        $body = $mapValue['messages'][0][$typeBody];

        if($typeBody == 'interactive') {
            if(array_key_exists('type', $body)) {
                $typeBody = $body['type'];
                $body = $body[$typeBody];
                if($typeBody == 'button_reply') {
                    $this->type = 'reply';
                    if(array_key_exists('id', $body)) {
                        $this->body = $body['id'];
                    }
                }
            }
        }

        if($typeBody == 'text') {
            if(array_key_exists('body', $body)) {
                $this->type = 'text';
                $this->body = $body['body'];
            }
        }
        
        if($typeBody == 'image') {
            if(array_key_exists('mime_type', $body)) {
                $this->type = 'image';
                $this->body = $body['id'];
            }
        }

        $this->canResponse = true;
    }

    /** */
    private function extractWaId(String $data) : void
    {
        $this->waId = $data;
        if(mb_strpos($this->waId, '521') !== false) {
            $this->waId = str_replace('521', '52', $this->waId);
        }
    }

    /** */
    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'context'=> $this->context,
            'waId'   => $this->waId,
            'campo'  => $this->campoResponsed,
            'body'   => $this->body,
            'type'   => $this->type,
            'expira' => $this->expira,
            'canResponse' => $this->canResponse,
            'timeStamp'   => $this->timeStamp
        ];
    }

}