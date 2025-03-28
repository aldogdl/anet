<?php

namespace App\Dtos;

use App\Enums\TypesWaMsgs;

class WaMsgDto
{
    public bool $isTest;
    public String $from;
    public String $id;
    public String $idDbSr;
    public String $context;
    public String $creado;
    public String $recibido;
    public TypesWaMsgs $tipoMsg;
    public String|array $content;
    public String $status;
    public String $subEvento;

    private String $eventName = 'whatsapp_api';

    /** */
    public function __construct(
        bool $isTest, String $from, String $id, String $idDbSr, String $context, String $creado, String $recibido,
        TypesWaMsgs $type, String|array $content, String $status, String $subEvento = ''
    )
    {
        $this->from      = $from;
        $this->id        = $id;
        $this->idDbSr    = $idDbSr;
        $this->context   = $context;
        $this->creado    = $creado;
        $this->recibido  = $recibido;
        $this->tipoMsg   = $type;
        $this->content   = $content;
        $this->status    = $status;
        $this->subEvento = $subEvento;
        $this->isTest    = $isTest;
    }

    /** */
    public function toArray(): array
    {
        return [
            'eventName' => $this->eventName,
            'subEvent'  => $this->subEvento,
            'from'      => $this->from,
            'id'        => $this->id,
            'idDbSr'    => $this->idDbSr,
            'context'   => $this->context,
            'creado'    => $this->creado,
            'recibido'  => $this->recibido,
            'type'      => $this->tipoMsg->value,
            'content'   => $this->content,
            'status'    => $this->status
        ];
    }

    /** Envio a comCore para Status de Whatsapp */
    public function toStt($get = false): array
    {
        $headers = HeaderDto::event([], $this->subEvento);
        $headers = HeaderDto::source($headers, $this->eventName);
        $headers = HeaderDto::waId($headers, $this->from);
        $headers = HeaderDto::includeBody($headers, false);
        $headers = HeaderDto::recived($headers, $this->recibido);
        $headers = HeaderDto::wamid($headers, $this->id);
        if($this->idDbSr != '') {
            $headers = HeaderDto::idDB($headers, $this->idDbSr);
        }
        if($this->context != '') {
            if($this->id != $this->context) {
                $headers = HeaderDto::context($headers, $this->context);
            }
        }
        // Para evitar duplicar codigo, se usa $get para retornar las cabeceras
        // que son repetitivas en la mayoria de los casos donde whatsapp esta
        // involucrado, la mayoria de los req. llevan los valores anteriores
        if($get) {
            return $headers;
        }

        if(array_key_exists('stt', $this->content)) {
            $headers = HeaderDto::setValue($headers, $this->content['stt']);
        }

        if(count($this->content) > 1) {
            if(array_key_exists('expi', $this->content)) {
                $headers = HeaderDto::campoValor($headers, '', 'Conv', $this->content['conv']);
                $headers = HeaderDto::campoValor($headers, '', 'Expi', $this->content['expi']);
                $headers = HeaderDto::campoValor($headers, '', 'Type', $this->content['type']);
            }
        }
        return ['header' => $headers];
    }

    /** Envio a anetTrack para Inicio de Sesion */
    public function toInit(String $message = ''): array
    {
        $headers = HeaderDto::event([], $this->subEvento);
        $headers = HeaderDto::source($headers, $this->eventName);
        $headers = HeaderDto::waId($headers, $this->from);
        $headers = HeaderDto::includeBody($headers, false);
        $headers = HeaderDto::recived($headers, $this->recibido);
        return $headers;
    }
}
