<?php

namespace App\Controller\SCP;

use App\Repository\OrdenesRepository;
use App\Service\CentinelaService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CentinelaController extends AbstractController
{

    #[Route('scp/centinela/ordenes-asignadas/', methods:['post'])]
    public function seveDataContact(
        Request $req,
        CentinelaService $centinela,
        OrdenesRepository $ordenes
    ): Response
    {   
        $result = ['abort' => true, 'msg' => 'error', 'body' => 'ERROR, No se recibieron datos.'];
        $data = json_decode( $req->request->get('data'), true );
        if(array_key_exists('info', $data)) {

            foreach ($data['info'] as $idAvo => $ords) {
                $result = $ordenes->asignarOrdenesToAvo((integer) $idAvo, $ords);
                if($result['abort']) {
                    break;
                }
            }
            if(!$result['abort']) {
                $centinela->asignarOrdenes($data);
            }
        }
        return $this->json($result);
    }

}
