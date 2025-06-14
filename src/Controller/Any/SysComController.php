<?php

namespace App\Controller\Any;

use App\Entity\UsCom;
use App\Repository\UsComRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\GetDataShop;
use App\Service\Pushes;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;

#[Route('/sys-com')]
class SysComController extends AbstractController
{
    /** Datos para any shop */
    #[Route('/get-data-any', methods: ['post'])]
    public function getDataAnyShop(Request $req, GetDataShop $shop): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['body' => 'Ok, gracias'], 400);
        }

        $data = $req->getContent();
        if(!$data) {
            return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
        }

        $data = json_decode($data, true);
        if(!array_key_exists('slug', $data) || !array_key_exists('dev', $data)) {
            return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
        }

        $res = $shop->getSimpleData($data);
        return $this->json($res);
    }

    /** */
    #[Route('/test', methods: ['post'])]
    public function test(Request $req, GetDataShop $shop): Response
    {
        return new Response(400);
    }

    /** */
    #[Route('/update-data-com', methods: ['post'])]
    public function updateDataCom(Request $req, UsComRepository $em): Response
    {
        if( $req->getMethod() == 'POST' ) {
            $data = $req->getContent();
            if(!$data) {
                return new Response(403);
            }

            $data = json_decode($data, true);
            if(array_key_exists('dev', $data)) {
                $obj = new UsCom();
                $obj->fromJson($data);
                $res = $em->updateDataCom($obj);
                return $this->json(['abort' => false, 'body' => $res]);
            }else{
                return $this->json(['abort' => true, 'body' => 'X Faltaron datos, Inténtalo nuevamente']);
            }
        }
        return new Response(400);
    }

    /** 
     * Si el cliente falla en enviar desde el FRM la notif a core, este mismo
     * hace reintentos para que core este enterado del nuevo item
    */
    #[Route('/push-core', methods: ['post'])]
    public function sendPushToCore(Request $req, UsComRepository $em, Pushes $push): Response 
    {
        $data = json_decode($req->getContent(), true);
        if(array_key_exists('code', $data)) {

            $how = file_get_contents($this->getParameter('report'));
            $token = $em->getTokenByWaId($how);
            $notif = Notification::create('Refuerzo de Solicitud', $data['code'], '');
            $result = $push->sendTo($token, $notif, ['ownApp' => $data['slugApp']]);
            if(array_key_exists('sended', $result)) {
                return $this->json(['abort' => false, 'id' => $result['sended']['name']]);
            }
        }
        
        return $this->json([]);
    }

    /** Desde el core subimos los datos de com-int */
    #[Route('/set-comloc', methods: ['post'])]
    public function setComLoc(Request $req): Response 
    {
        if($req->getMethod() == 'POST') {
            $header = $req->headers->get('any-token') ?? '';
            if($header == $this->getParameter('getAnToken')) {
                $data = $req->getContent();
                if($data) {
                    $scm = $this->getParameter(AnyPath::$COMMLOC);
                    file_put_contents(Path::canonicalize($scm), $data);
                    return $this->json(['abort' => false]);
                }
            }
        }
        return $this->json(['abort' => true]);
    }

}
