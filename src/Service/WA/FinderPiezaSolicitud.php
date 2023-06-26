<?php

namespace App\Service\WA;

use App\Service\WA\Dom\CotizandoPzaDto;

class FinderPiezaSolicitud {

    public bool $isOkSend = false;
    public String $stepFinder = '';

    private $pathToSols = '';

    /** */
    public function __construct(String $pathToSol)
    {
        $this->pathToSols = $pathToSol;
    }

    /** 
     * Analizamos en que paso se quedo en la pieza que se estaba
     * cotizando para recordarle al cotizador tanto pieza como el
     * campo.
    */
    public function determinarPzaAndStepCot(array $cot) : String {
        
        $msg = '😔 _Lo sentimos mucho_, la pieza que deseas cotizar';
        $msg = $msg.' *ya no esta disponible*. Pronto recibirás nuevas';
        $msg = $msg.' solicitudes. 😃👍';

        $obj = new CotizandoPzaDto(false);
        $obj->fromArray($cot);

        $filename = $this->pathToSols.'/'.$obj->idSol.'.json';
        if(is_file($filename)) {

            $content = file_get_contents($filename);
            if($content) {
                $content = json_decode($content, true);
            }
            if(count($content) > 0) {
                $msg = $this->buildMsgOfPza($content, $obj);
                
                foreach ($cot as $key => $value) {
                    if($value == 'wait') {
                        $this->stepFinder = $key;
                        break;
                    }
                }
            }
        }

        return $msg;
    }

    /** */
    private function buildMsgOfPza(array $sol, CotizandoPzaDto $coti) : String 
    {
        $pza = [];
        if(array_key_exists('piezas', $sol)) {
            
            foreach ($sol['piezas'] as $pieza) {
                if($pieza['id'].'' == $coti->idPza) {
                    $pza = $pieza;
                    break;
                }
            }
        }

        if($pza) {
            $this->isOkSend = true;
            $msg = '*'.$pza['piezaName']."* \n".$pza['posicion']."\n".$pza['lado']."\n";
            return $msg.'🚘 *'.$sol['modelo']['nombre'].'* '.$sol['anio'].' '.$sol['marca']['nombre'];
        }

        return '';
    }

}