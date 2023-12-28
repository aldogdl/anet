<?php

namespace App\Entity;

class WaMsgMdl
{
    public String $from;
    public String $id;
    public String $context;
    public String $creado;
    public String $recibido;
    public String $type;
    public String $message;
    public String $status;
    public String $sesKduk;
    public String $subEvento;

    /** */
    public function __construct(
        String $from, String $id, String $context, String $creado, String $recibido,
        String $type, String $message, String $status, String $sesKduk = '', String $subEvento = '',
    )
    {
        $this->from      = $from;
        $this->id        = $id;
        $this->context   = $context;
        $this->creado    = $creado;
        $this->recibido  = $recibido;
        $this->type      = $type;
        $this->message   = $message;
        $this->subEvento = $subEvento;
        $this->status    = $status;
        $this->sesKduk   = $sesKduk;
    }

    /** */
    public function toArray(): array
    {
        return [
            'from'      => $this->from,
            'id'        => $this->id,
            'context'   => $this->context,
            'creado'    => $this->creado,
            'recibido'  => $this->recibido,
            'type'      => $this->type,
            'message'   => $this->message,
            'subEvento' => $this->subEvento,
            'status'    => $this->status,
            'sesKduk'   => $this->sesKduk,
        ];
    }

}
