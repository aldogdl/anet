<?php

namespace App\Controller\Any;

use App\Entity\UsCom;
use App\Repository\UsComRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\GetDataShop;
use App\Service\Any\PublicAssetUrlGenerator;
use App\Service\DataSimpleMlm;
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
    /** */
    #[Route('/test', methods: ['get'])]
    public function test(Request $req, PublicAssetUrlGenerator $urlGen): Response
    {
        $prodSols = $this->getParameter(AnyPath::$PRODSOLS);
        $originalFilename = $req->query->get('file');
        $path = Path::canonicalize($prodSols.'/'.$originalFilename);

        if (!file_exists($path)) {
            return $this->json(['abort' => true, 'body' => 'X No existe archivo' . $path], 402);
        }else{
            $url = $urlGen->generate($path);
            return $this->json(['abort' => true, 'body' => 'Ok:' . $url], 200);
        }
        return new Response(400);
    }

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

    /** */
    #[Route('/dta-ctc-list', methods: ['GET'])]
    public function listarArchivos(Request $req): Response
    {
        $carpeta = $this->getParameter('dtaCtc');

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        $indexFile = "index_dta_ctc.json";

        if (file_exists($indexFile)) {
            // Si el index ya existe, lo leemos
            $contenido = file_get_contents($indexFile);
            $archivos = json_decode($contenido, true);
        } else {
            // Si no existe, lo generamos
            $archivos = [];

            foreach (scandir($carpeta) as $archivo) {
                if (
                    is_file("$carpeta/$archivo") &&
                    pathinfo($archivo, PATHINFO_EXTENSION) === 'json'
                ) {
                    $archivos[] = [
                        'ctc' => $archivo,
                        'modificado' => date('c', filemtime("$carpeta/$archivo")),
                    ];
                }
            }

            // Guardamos el índice generado
            file_put_contents($indexFile, json_encode($archivos));
        }

        return $this->json([
            'status' => 'ok',
            'archivos' => $archivos,
        ]);
    }

    /** Datos para vinc con ml */
    #[Route('/get-data-ownml', methods: ['post'])]
    public function getDataOwnMl(Request $req, Fsys $fsys): Response
    {
        if($req->getMethod() != 'POST') {
            return $this->json(['body' => 'Ok, gracias'], 400);
        }

        $data = $req->getContent();
        if(!$data) {
            return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
        }

        $data = json_decode($data, true);
        if(!array_key_exists('slug', $data)) {
            return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
        }

        $ctcLog = $fsys->get(AnyPath::$DTACTCLOG, $data['slug'].'.json');
        $apiml = $fsys->get(AnyPath::$ANYMLM, '');
        return $this->json([
            'ctcLog' => $ctcLog,
            'apiml' => $apiml,
        ]);
    }

    /** */
    #[Route('/update-meli', methods: ['POST'])]
    public function updateDataMeli(Request $req): Response
    {
        $data = $req->getContent();
        if($data) {
            $map = json_decode($data, true);
            if(array_key_exists('slug', $map)) {
                $logs = $this->getParameter('dtaCtcLog');
                $path = Path::canonicalize($logs.'/'.$map['slug'].'.json');
                file_put_contents($path, json_encode($map));
                return $this->json(['abort' => false]);
            }
        }
        return $this->json(['abort' => true]);
    }

    /** 
     * Validamos que el slug de la empresa este entre las registradas
    */
    #[Route('/validate-nickname', methods: ['HEAD'])]
    public function validateNick(Request $req): Response
    {
        $data = $req->headers->get('x-nickname');
        if($data) {
            $exp = $this->getParameter('dtaCtc');
            $path = Path::canonicalize($exp.'/'.$data.'.json');
    
            $exists = file_exists($path);
            return new Response(
                '',
                $exists ? 200 : 404,
                ['X-Nickname-Valid' => $exists ? '1' : '0']
            );
        }
        return new Response('', 403, ['X-Nickname-Valid' => '0']);
    }

    /** 
     * Desvinculamos la relacion entre app meli
    */
    #[Route('/desvincular-meli', methods: ['POST'])]
    public function desvincularMeli(Request $req, DataSimpleMlm $mlm): Response
    {
        $data = $req->getContent();
        if($data) {
            $data = json_decode($data, true);
            if(array_key_exists('slug', $data)) {
                $res = $mlm->desvincularMlm($data);
                return $this->json(['result' => $res]);
            }
        }
        return $this->json(['result' => false]);
    }

    /** 
     * Validamos que el slug de la empresa este entre las registradas
    */
    #[Route('/update-data-user', methods: ['POST'])]
    public function updateDataUser(Request $req): Response
    {
        $data = $req->getContent();
        if($data) {
            $data = json_decode($data, true);
            if(array_key_exists('slug', $data)) {
                $exp = $this->getParameter('dtaCtc');
                $path = Path::canonicalize($exp.'/'.$data['slug'].'.json');
                file_put_contents($path, json_encode($data));
                return $this->json(['abort' => false]);
            }
        }
        return $this->json(['abort' => true], 403);
    }

}
