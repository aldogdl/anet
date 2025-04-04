<?php

namespace App\Service;

use App\Service\MyFsys;

class SincronizerItem
{

    private MyFsys $fSys;

    public function __construct(MyFsys $fs)
    {
        $this->fSys = $fs;    
    }

    /** */
    public function build(array $data): void
    {
        $timestamp = mktime(0, 0, 0, date('m'), date('d'), date('Y')).'';
        // Recuperamos el archivo
        $content = $this->fSys->getContent('sincDev', $data['ownWaId'].'.json');
        if(count($content) == 0) {
            $content = [
                'publica' => [],
                'solicita' => [$timestamp => [$data['id']]]
            ];
        }else{
            if($data['type'] == 'publica') {
                // Publica => son respuestas de una solicitud
                $content[$data['type']][$data['idCot']][] = $data['id'];
            }else{
                $content[$data['type']][$timestamp][] = $data['id'];
            }
        }
        $this->fSys->setContent('sincDev', $data['ownWaId'].'.json', $content);
    }

    /** */
    public function get(String $waId): array
    {

        // Recuperamos el archivo
        $content = $this->fSys->getContent('sincDev', $waId.'.json');
        if(count($content) == 0) {
            return ['publica' => [], 'solicita' => []];
        }

        $solicita = $content['solicita'];
        $publica = $content['publica'];
        $hasMorFive = count($solicita);
        // Si hay mas de 5 solicitudes, eliminamos las mas viejas
        if($hasMorFive > 5) {
            // Ordena el arreglo por clave (timestamp)
            ksort($solicita);
            $solicita = array_slice($solicita, -5);
            $nuevasPublicas = [];
            foreach($solicita as $timestamp => $idItems) {
                $rota = count($idItems);
                for ($i=0; $i < $rota; $i++) { 
                    $idCot = $idItems[$i];
                    if(array_key_exists($idCot, $publica)) {
                        $nuevasPublicas[$idCot] = $publica[$idCot];
                    }
                }
            }
            $publica = $nuevasPublicas;
        }
        
        $content['solicita'] = $solicita;
        $content['publica'] = $publica;

        $this->fSys->setContent('sincDev', $waId.'.json', $content);
        return $content;
    }

}
