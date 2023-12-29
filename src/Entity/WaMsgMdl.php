<?php

namespace App\Entity;

class WaMsgMdl
{
    public String $from;
    public bool $isFromAnet;
    public String $id;
    public String $context;
    public String $creado;
    public String $recibido;
    public String $type;
    public String $message;
    public String $status;
    public String $subEvento;

    /** */
    public function __construct(
        String $from, String $id, String $context, String $creado, String $recibido,
        String $type, String $message, String $status, String $subEvento = '',
    )
    {
        $this->from      = $from;
        $this->isFromAnet= false;
        $this->id        = $id;
        $this->context   = $context;
        $this->creado    = $creado;
        $this->recibido  = $recibido;
        $this->type      = $type;
        $this->message   = $message;
        $this->status    = $status;
        $this->subEvento = $subEvento;
    }

    /** */
    public function toArray(): array
    {
        return [
            'from'      => $this->from,
            'isFromAnet'=> $this->isFromAnet,
            'id'        => $this->id,
            'context'   => $this->context,
            'creado'    => $this->creado,
            'recibido'  => $this->recibido,
            'type'      => $this->type,
            'message'   => $this->message,
            'subEvento' => $this->subEvento,
            'status'    => $this->status
        ];
    }

}
