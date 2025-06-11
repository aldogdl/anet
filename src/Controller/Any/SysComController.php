<?php

namespace App\Controller\Any;

use App\Dtos\DataShopDto;
use App\Entity\UsCom;
use App\Repository\UsComRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\IkuGenerator\GeneratorIku;
use App\Service\Pushes;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;

#[Route('/sys-com')]
class SysComController extends AbstractController
{
    /** Datos para any shop */
    #[Route('/get-data-any', methods: ['post'])]
    public function getDataAnyShop(Request $req, DataShopDto $shop): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['body' => 'Ok, gracias'], 400);
        }

        $data = $req->getContent();
        if(!$data) {
            return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
        }

        $data = json_decode($data, true);
        if(!array_key_exists('slug', $data) || !array_key_exists('dev', $data)) {
            return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
        }

        $res = $shop->getSimpleData($data);
        return $this->json($res);
    }

    /** */
    #[Route('/set-user-form', methods: ['post'])]
    public function setUserFromForm(Request $req, UsComRepository $em): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['body' => 'Ok, gracias'], 400);
        }
        $data = $req->getContent();
        if($data) {
            $data = json_decode($data, true);
            $ikuGenerator = new GeneratorIku();
            $iku = $ikuGenerator->generate();
            $data['iku'] = $iku;
            if(array_key_exists('n', $data)) {
                $id = $em->setUserFromForm($data);
                return $this->json(['abort' => false, 'body' => $id]);
            }
        }
        return $this->json(['abort' => true], 403);
    }

    /** */
    #[Route('/update-data-com', methods: ['post'])]
    public function updateDataCom(Request $req, UsComRepository $em, DataShopDto $shop): Response
    {
        if( $req->getMethod() == 'POST' ) {
            $data = $req->getContent();
            if(!$data) {
                return new Response(500);
            }

            $data = json_decode($data, true);
            if(array_key_exists('dev', $data)) {
                $obj = new UsCom();
                $obj->fromJson($data);
                $obj = $em->updateDataCom($obj);
                if($obj != null) {
                    if($obj->getRole() == 'b') {
                        $data = $shop->getMetaBussiness($obj);
                    }else{
                        $data = $shop->getMetaCustomer($obj);
                    }
                    return $this->json($data);
                }else{
                    return $this->json(['abort' => true, 'body' => 'X Error 401, Inténtalo nuevamente']);
                }
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
