<?php

namespace App\Dtos;

class ConmDto
{
    public String $uriToWhatsapp = 'https://graph.facebook.com/v17.0/';
    
    public String $token;
    public String $to;
    public String $waId;
    public String $context;

    /** */
    public function __construct(array $content)
    {
        $this->token = $content[$content['modo']]['tk'];
        if(mb_strpos($this->token, 'aldo_') !== false) {
            $this->token = str_replace('aldo_', '', $this->token);
        }
        $this->to = '';
        $this->waId = '';
        $this->context = '';
        $this->uriToWhatsapp = $this->uriToWhatsapp . $content[$content['modo']]['id'];
    }

    /** */
    public function setMetaData(WaMsgDto $waMsg)
    {
        $this->waId = $waMsg->from;
        $this->context = $waMsg->context;
        if(mb_strpos($waMsg->from, '521') !== false) {
            $this->to = str_replace('521', '52', $waMsg->from);
        }
    }

    /** */
    public function toArray()
    {
        return [
            'uriBase' => $this->uriToWhatsapp,
            'token'   => $this->token,
            'to'      => $this->to,
            'waId'    => $this->waId
        ];
    }

}
