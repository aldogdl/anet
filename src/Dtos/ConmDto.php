<?php

namespace App\Dtos;

class ConmDto
{
    public String $uriBase = 'https://graph.facebook.com/v17.0/';
    
    public String $token;
    public String $to;
    public String $waId;
    public String $context;
    public String $evento;
    public String $subEvento;
    public String $sendReportTo;
    public array $reporTo = [
        'anet_track' => '523320396725', 'event_core' => '523322060352'
    ];

    /** */
    public function __construct(WaMsgDto $waMsg, array $content)
    {
        $this->waId = $waMsg->from;
        $this->context = $waMsg->context;
        $this->evento = '';
        $this->subEvento = $waMsg->subEvento;
        $this->token = $content[$content['modo']]['tk'];
        if(mb_strpos($this->token, 'aldo_') !== false) {
            $this->token = str_replace('aldo_', '', $this->token);
        }

        if(mb_strpos($waMsg->from, '521') !== false) {
            $this->to = str_replace('521', '52', $waMsg->from);
        }

        $this->uriBase = $this->uriBase . $content[$content['modo']]['id'];
    }

    /** */
    public function setEventAndSegRoute(String $event, String $segmento)
    {
        $this->evento = $event;
        $this->sendReportTo = $this->reporTo[$segmento];
    }

    /** */
    public function toArray()
    {
        return [
            'uriBase' => $this->uriBase,
            'token'   => $this->token,
            'to'      => $this->to,
            'waId'    => $this->waId
        ];
    }

}
