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
    /**
     * Obtenemos el request contenido decodificado como array
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function toArray(Request $req, String $campo): array
    {
        $content = $req->request->get($campo);
        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException(sprintf('No se puede decodificar el body, "%s".', get_debug_type($content)));
        }

        if (!\is_array($content)) {
            throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
        }
        return $content;
    }

    /** */
    #[Route('/get-data-any/{slug}', methods: ['get'])]
    public function getDataAnyShop(Request $req, DataShopDto $shop, String $slug): Response
    {
        if($req->getMethod() != 'GET') {
            return $this->json(['body' => 'Ok, gracias'], 400);
        }
        $dev = $req->query->get('dev') ?? '';
        if(!$slug || !$dev) {
            return $this->json(['abort' => true, 'body' => 'Faltan datos en el parametro'], 403);
        }
        $res = $shop->exec($slug, $dev);
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
    #[Route('/update-tkfb', methods: ['post'])]
    public function updateTkFb(Request $req, UsComRepository $em): Response
    {
        if( $req->getMethod() == 'POST' ) {
            $data = json_decode($req->getContent(), true);
            if(array_key_exists('dev', $data)) {
                $obj = new UsCom();
                $obj->fromJson($data);
                $res = $em->updateTkFb($obj);
                return $this->json($res);
            }else{
                return new Response(500);
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
