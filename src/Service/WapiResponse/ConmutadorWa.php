<?php

namespace App\Service\WapiResponse;

use App\Entity\WaMsgMdl;

class ConmutadorWa
{
    public String $uriBase = 'https://graph.facebook.com/v17.0/';
    public String $token;
    public String $to;
    public String $waid;

    public string $type = '';
    public array $body = [];
    public $bodyRaw = '';

    /** */
    public function __construct(String $waId, String $path)
    {
        $fileConm = file_get_contents($path);

        if($fileConm) {

            $content = json_decode($fileConm, true);

            $this->token = $content[$content['modo']]['tk'];
            if(mb_strpos($this->token, 'aldo_') !== false) {
                $this->token = str_replace('aldo_', '', $this->token);
            }

            $this->waid = $waId;
            if(mb_strpos($this->waid, '521') !== false) {
                $this->to = str_replace('521', '52', $this->waid);
            }

            $this->uriBase = $this->uriBase . '/' . $content[$content['modo']]['id'];
        }
    }

    /** */
    public function setBody(String $tipoBody, array $bodySend)
    {
        $this->type = $tipoBody;
        $this->body = $bodySend;
    }

    /**
     * Guardamos el id del mensaje enviando a whatsapp y colocamos el from desde ANET
    */
    public function setIdToMsgSended(WaMsgMdl $recibido, array $response): WaMsgMdl
    {
        $id = '';
        $from = '';
        if($response['statuscode'] == 200) {
            $response = $response['body'];
            if(array_key_exists('messages', $response)) {
                $id = $response['messages'][0]['id'];
            }
            if(array_key_exists('contacts', $response)) {
                $from = $response['contacts'][0]['wa_id'];
            }
        }

        $creado = round(microtime(true) * 1000);
        return new WaMsgMdl(
            'ANET',
            $id,
            $recibido->id,
            $creado,
            $creado,
            $this->type,
            $this->bodyRaw,
            'sent',
            '',
            'response_'.$recibido->subEvento
        );
    }

    /** */
    public function toArray()
    {
        return [
            'uriBase' => $this->uriBase,
            'token'   => $this->token,
            'to'      => $this->to,
            'waid'    => $this->waid,
            'type'    => $this->type,
            'body'    => $this->body,
        ];
    }

}
