<?php

namespace App\Service\WapiProcess;

class DecodeTemplate {

    /**
     * La key debe ser el codigo y el value el campo del array del cotProgress
     * NOTA: Si se requiere un campo que no este en el cotProgress este debe
     * agregarse en la clase TrackFileCot
    */
    private array $codesAnet = [
        '{:uuid}'  => 'idItem',
        '{:token}' => 'token',
        '{:detalles}' => 'detalles',
    ];
    private array $cotProgress;

    /** */
    public function __construct(array $cotProgress)
    {
        $this->cotProgress = $cotProgress;
    }

    /** */
    public function decode(array $template): array
    {
        if(array_key_exists('type', $template)) {

            $tipo = $template['type'];
            $keys = array_keys($template[$tipo]);
            $rota = count($keys);

            for ($i=0; $i < $rota; $i++) {
                if($keys[$i] != 'type') {
                    if(is_array($template[$tipo][$keys[$i]])) {

                        if(array_key_exists('buttons', $template[$tipo][$keys[$i]])) {

                            $vueltas = count($template[$tipo][$keys[$i]]['buttons']);
                            for ($b=0; $b < $vueltas; $b++) {
                                $typ = $template[$tipo][$keys[$i]]['buttons'][$b]['type'];
                                $clvs = array_keys($template[$tipo][$keys[$i]]['buttons'][$b][$typ]);
                                $giro = count($clvs);
                                for ($c=0; $c < $giro; $c++) { 
                                    $template[$tipo][$keys[$i]]['buttons'][$b][$typ][$clvs[$c]] = $this->fetchCode(
                                        $template[$tipo][$keys[$i]]['buttons'][$b][$typ][$clvs[$c]]
                                    );
                                }
                            }

                        }else{
                            if(array_key_exists('text', $template[$tipo][$keys[$i]])) {
                                $template[$tipo][$keys[$i]]['text'] = $this->fetchCode(
                                    $template[$tipo][$keys[$i]]['text']
                                );
                            }
                        }
                    }
                }
            }
        }
        return $template;
    }


    /** */
    private function fetchCode(String $txt): String
    {
        foreach ($this->codesAnet as $code => $campo) {
            if(mb_strpos($txt, $code) !== false) {
                $txt = str_replace($code, $this->cotProgress[$campo], $txt);
            }
        }
        return $txt;
    }
}