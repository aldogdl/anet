<?php

namespace App\Dtos;

use App\Enums\TypesWaMsgs;

class WaMsgDto
{
    public bool $isTest;
    public String $from;
    public String $id;
    public String $idItem;
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
        bool $isTest, String $from, String $id, String $idItem, String $context, String $creado, String $recibido,
        TypesWaMsgs $type, String|array $content, String $status, String $subEvento = ''
    )
    {
        $this->from      = $from;
        $this->id        = $id;
        $this->idItem    = $idItem;
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
            'idItem'    => $this->idItem,
            'context'   => $this->context,
            'creado'    => $this->creado,
            'recibido'  => $this->recibido,
            'type'      => $this->tipoMsg->value,
            'content'   => $this->content,
            'status'    => $this->status
        ];
    }

    /** Envio a eventCore para el proceso de cotizacion */
    public function toMini(array $body = []): array
    {
        $cuerpo = [
            'eventName' => $this->eventName,
            'subEvent'  => $this->subEvento,
            'from'      => $this->from,
            'idItem'    => $this->idItem
        ];
        if(count($body) > 0) {
            $cuerpo['body'] = $body;
        }
        return $cuerpo;
    }

    /** Envio a eventCore para Status de Whatsapp */
    public function toStt(): array
    {
        $headers = HeaderDto::event([], $this->subEvento);
        $headers = HeaderDto::source($headers, $this->eventName);
        $headers = HeaderDto::waId($headers, $this->from);
        $headers = HeaderDto::includeBody($headers, false);
        $headers = HeaderDto::recived($headers, $this->recibido);
        $headers = HeaderDto::setValue($headers, $this->content['stt']);
        $headers = HeaderDto::wamid($headers, $this->id);
        if($this->idItem != '') {
            $headers = HeaderDto::idItem($headers, $this->idItem);
        }
        if($this->context != '') {
            $headers = HeaderDto::context($headers, $this->context);
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

    /** Envio a comCore para Inicio de Sesion */
    public function toInit(): array
    {
        $headers = HeaderDto::event([], $this->subEvento);
        $headers = HeaderDto::source($headers, $this->eventName);
        $headers = HeaderDto::waId($headers, $this->from);
        $headers = HeaderDto::includeBody($headers, false);
        $headers = HeaderDto::recived($headers, $this->recibido);
        return ['header' => $headers];
    }
}
