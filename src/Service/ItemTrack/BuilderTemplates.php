<?php

namespace App\Service\ItemTrack;

use App\Dtos\WaMsgDto;
use App\Service\ItemTrack\Fsys;

class BuilderTemplates {

    private Fsys $fSys;
    private WaMsgDto $waMsg;
    private $opciones = array(
        array(
            'head' => '😃👍 ¡Genial!',
            'body' => 'Puedes enviar *más fotos* o seguir con la *descripción* del estado general.'
        ),
        array(
            'head' => '😃👌 ¡Perfecto!',
            'body' => 'Puedes enviar *más fotos* o seguir con los *detalles* de la pieza.'
        ),
        array(
            'head' => '😊👍 ¡Estupendo!',
            'body' => 'Puedes añadir *más fotos* o avanzar con los *detalles* de la autoparte.'
        ),
        array(
            'head' => '😄👍 ¡Fantástico!',
            'body' => 'Puedes enviar *más fotos* o continuar con la información sobre los *detalles*.'
        ),
        array(
            'head' => '😃👌 ¡Excelente!',
            'body' => 'Puedes agregar *más fotos* o seguir con los *detalles* del producto.'
        ),
        array(
            'head' => '😊👍 ¡Maravilloso!',
            'body' => 'Puedes incluir *más fotos* o avanzar con los *detalles* de la pieza.'
        ),
        array(
            'head' => '😃👌 ¡Genial!',
            'body' => '¿Deseas subir *más fotos*? o avanzar con los *detalles* de la autoparte.'
        ),
        array(
            'head' => '😄👍 ¡Fantástico!',
            'body' => 'Puedes añadir *más fotos* o continuar con la descripción de los *detalles*.'
        ),
        array(
            'head' => '😊👌 ¡Increíble!',
            'body' => 'Puedes enviar *más fotos* o avanzar con los *detalles* del producto.'
        ),
        array(
            'head' => '😃👍 ¡Excelente!',
            'body' => '¿Listo para *más fotos*? o prefieres continuar con la descripción del *estado general*?.'
        )
    );
    
    public function __construct(Fsys $fsys, WaMsgDto $wamsg)
    {
        $this->fSys = $fsys;
        $this->waMsg = $wamsg;
    }

    /** */
    public function exe(String $template, String $idDbSr = ''): array
    {
        $content = [];
        try {
            $content = $this->fSys->getContent('waTemplates', $template.'.json');
        } catch (\Throwable $th) {}

        if(count($content) > 0) {
            $changeOnlyBtns = ['sfto', 'sdta', 'cext', 'nfto'];
            if(in_array($template, $changeOnlyBtns)) {
                return $this->changeOnlyIdByBtns($content, $idDbSr);
            }
        }
        
        return $content;
    }

    /** */
    public function editForDetalles(array $content): array
    {
        $rand = array_rand($this->opciones);
        if(array_key_exists('type', $content)) {
            if(array_key_exists('body', $content[$content['type']])) {
                $content[$content['type']]['body']['text'] = $this->opciones[$rand]['body'];
            }
            if(array_key_exists('header', $content[$content['type']])) {
                $content[$content['type']]['header']['text'] = $this->opciones[$rand]['head'];
            }
        }
        return $content;
    }

    /** */
    private function changeOnlyIdByBtns(array $tmp, String $idDbSr = ''): array
    {
        $idDbSr = ($idDbSr == '') ? $this->waMsg->idDbSr : $idDbSr;
        if(array_key_exists('interactive', $tmp)) {

            $btns = $tmp['interactive']['action']['buttons'];
            $rota = count($btns);
            if($rota > 0) {
                for ($i=0; $i < $rota; $i++) {
                    $id = $tmp['interactive']['action']['buttons'][$i]['reply']['id'];
                    $id = str_replace('{:uuid}', $idDbSr, $id);
                    $tmp['interactive']['action']['buttons'][$i]['reply']['id'] = $id;
                }
            }
            return $tmp;
        }
        return [];
    }

}