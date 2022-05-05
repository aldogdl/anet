<?php

namespace App\Controller\SCM;

use App\Repository\NG2ContactosRepository;
use App\Repository\ScmOrdpzaRepository;
use App\Service\CentinelaService;
use App\Service\ScmService;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class GetController extends AbstractController
{

    /**
     * Obtenemos el request contenido decodificado como array
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function toArray(Request $req, String $campo, String $content = '0'): array
    {
        if($content == '0') {
            $content = $req->request->get($campo);
        }
        try {
            $content = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException('No se puede decodificar el body.', $e->getCode(), $e);
        }
        if (!\is_array($content)) {
            throw new JsonException(sprintf('El contenido JSON esperaba un array, "%s" para retornar.', get_debug_type($content)));
        }
        return $content;
    }

    /**
     * Revisamos si en el centinela hay una nueva version y descargamos el
     * último contenido del archivo scm
     */
    #[Route('scm/has-updates/{verCenti}/', methods:['get'])]
    public function hasUpdates(
        CentinelaService $centinela,
        ScmService $scm,
        string $verCenti
    ): Response
    {
        $result['centinela'] = $centinela->isSameVersionAndGetVersionNew($verCenti);
        $result['scm'] = $scm->getContent();
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $result
        ]);
    }

    /**
     *
     */
    #[Route('scm/get-scmordpza/{item}/', methods:['get'])]
    public function getRequerimientos(
        ScmOrdpzaRepository $em,
        ScmService $scm,
        CentinelaService $centinela,
        String $item
    ): Response
    {
        $response = ['abort'=>false, 'msg' => 'ok', 'body' => []];

        // Obtenemos el contenido completo del archivo de mensajeria.
        $tasks = $scm->getContent();
        // Obtenemos el contenido completo de los manifiesto de las campañas.
        $mensajes = $this->toArray(
            new Request(), '', file_get_contents($this->getParameter('scmMsgsDef'))
        );

        $result = [];
        // Recuperamos los ids requeridos
        switch ($item) {
            case 'ordenes':
                $dql = $em->getMsgByOrden($tasks[$item]);
                $result = $dql->getArrayResult();
                $rota = count($result);
                if($rota > 0) {
                    $fileCenti = $centinela->getContent();
                    for ($i=0; $i < $rota; $i++) {
                        $piezasIds = $fileCenti['piezas'][$result[$i]['orden']['id']];
                        $vltas = count($piezasIds);
                        for ($p=0; $p < $vltas; $p++) {
                            $result[$i]['orden']['pzas'][] = [
                                'idP' => $piezasIds[$p],
                                'ctz' => $fileCenti['stt'][ $piezasIds[$p] ]['ctz'],
                            ];
                        }
                    }
                    $pathSmg = Path::join(
                        $this->getParameter('scmMsgs'), $mensajes[$result[0]['msg']]['file'].'.txt'
                    );
                    $response['body'] = [
                        'tit' => $mensajes[$result[0]['msg']]['tit'],
                        'msg' => file_get_contents($pathSmg),
                        'task'=> $result
                    ];
                    $tasks = $scm->clean($item);
                }else{
                    $response['abort']= true;
                    $response['msg']  = 'ERROR';
                    $response['body'] = 'No se encontraron las ordenes ' . implode(',', $tasks[$item]);
                }
                break;
            default:
                # code...
                break;
        }

        return $this->json($response);
    }

    /**
     * Recuperamos los datos del contacto para almacenarlos en disco local.
     */
    #[Route('scm/get-contacto-byid/{idContac}/', methods:['get'])]
    public function getContactoById(
        NG2ContactosRepository $em,
        String $idContac
    ): Response
    {
        $dql = $em->getContactoById($idContac);
        $result = $dql->getScalarResult();
        $rota = count($result);
        return $this->json([
            'abort'=> ($rota > 0) ? false : true,
            'msg'  => ($rota > 0) ? 'ok' : 'Sin Resultados',
            'body' => ($rota > 0) ? $result[0] : []
        ]);
    }


}
