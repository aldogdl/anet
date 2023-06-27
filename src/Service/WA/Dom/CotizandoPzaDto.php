<?php

namespace App\Service\WA\Dom;

class CotizandoPzaDto {

    public String $idPza     = '0';
    public String $idSol     = '0';
    public String $idHive    = '0';
    public String $indexConv = '0';
    public String $accion    = '0';
    public String $fotos     = 'wait';
    public String $detalles  = 'wait';
    public String $costo     = 'wait';
    public String $grax      = 'wait';
    public bool $isTest      = false;

    /** */
    public function __construct(bool $isTest, String $hasIdBtn = '')
    {
        $this->isTest = $isTest;
        if($hasIdBtn != '') {
            $this->peelId($hasIdBtn);
        }
    }

    /**
     * Despedazamos el ID del boton reply para extraer los datos de
     * la solicitud y pieza que se esta respondiendo
     */
    public function peelId(String $idBtn) : void {

        //Ej. de Id del boton 35_652_778_168694385937819_cotizar
        $partes = explode('_', $idBtn);
        if(count($partes) > 4) {

            $this->indexConv = $partes[0];
            $this->idPza     = $partes[1];
            $this->idSol     = $partes[2];
            $this->accion    = $partes[4];
            $this->idHive    = $partes[3];
        }
    }

    /**
     * Hidratamos el objeto desde el archivo json
     */
    public function fromArray(array $data) : void
    {
        $this->idPza     = $data['idPza'];
        $this->idSol     = $data['idSol'];
        $this->idHive    = $data['idHive'];
        $this->indexConv = $data['indexConv'];
        $this->accion    = $data['accion'];
        $this->fotos     = $data['fotos'];
        $this->detalles  = $data['detalles'];
        $this->costo     = $data['costo'];
        $this->grax      = $data['grax'];
        $this->isTest    = $data['isTest'];
    }

    /** **/
    public function setDataResponse(WaMessageDto $msg) : WaMessageDto {

        $msg->idSol  = $this->idSol;
        $msg->idPza  = $this->idPza;
        $msg->idHive = $this->idHive;
        if($msg->campoResponsed == '') {
            $msg->campoResponsed = $this->accion;
        }
        return $msg;
    }

    /** **/
    public function toArray() : array {

        return [
            'idPza'     => $this->idPza,
            'idSol'     => $this->idSol,
            'idHive'    => $this->idHive,
            'indexConv' => $this->indexConv,
            'accion'    => $this->accion,
            'fotos'     => $this->fotos,
            'detalles'  => $this->detalles,
            'costo'     => $this->costo,
            'grax'      => $this->grax,
            'isTest'    => $this->isTest,
        ];
    }
}