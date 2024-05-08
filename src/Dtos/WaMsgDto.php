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

    /** */
    public function toMini(): array
    {
        return [
            'eventName' => $this->eventName,
            'subEvent'  => $this->subEvento,
            'from'      => $this->from,
            'idItem'    => $this->idItem
        ];
    }

    /** */
    public function toStt(): array
    {
        return [
            'eventName' => $this->eventName,
            'from'      => $this->from,
            'recibido'  => $this->idItem,
        ];
    }

    /** */
    public function toInit(): array
    {
        return [
            'eventName' => $this->eventName,
            'subEvent'  => $this->subEvento,
            'from'      => $this->from,
            'recibido'  => $this->recibido
        ];
    }
}
