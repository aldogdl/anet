<?php

namespace App\Controller\SCM;

use App\Repository\NG2ContactosRepository;
use App\Repository\ScmCampRepository;
use App\Entity\Ordenes;
use App\Service\CentinelaService;
use App\Service\ScmService;

use Symfony\Component\Filesystem\Path;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
     *
     */
    #[Route('scm/get-campaingof/{target}/', methods:['get'])]
    public function getCampainsOf(
        ScmCampRepository $em, ScmService $scm, CentinelaService $centinela,
        ManagerRegistry $doctrine, String $target
    ): Response
    {
      $response = ['abort' => false, 'msg' => 'ok', 'body' => []];

      // Obtenemos el contenido completo del archivo Targets.
      // Aqui conocemos cuales son los ids de las campañas nuevas
      $fileTargets = $scm->getContent();
      $dql = $em->getCampaingsOfTargetByIds($fileTargets[$target]);
      $campaings = $dql->getArrayResult();
      $rota = count($campaings);

      switch ($target) {

        case 'bundle':
          // PROTOCOLO requerido
          // {
          //  "id":"Es el id de la tabla donde se sacaran los datos del objetivo (target)",
          //  "class":"Es nombre de la clase donde se encuentra el id"
          //  "msg":"El nombre del archivo que contiene el mensaje a ser enviado"
          // }
          if($rota > 0) {

            $fileCenti = $centinela->getContent();
            // Obtenemos los targets de cada campaña
            for ($i=0; $i < $rota; $i++) {
              $emT = $doctrine->getRepository('App\\Entity\\'.$campaings[$i]['src']['class']);
              $result = $emT->getTargetById($campaings[$i]['src']['id']);
              if($result) {
                $campaings[$i]['target'] = $result[0];

                // Extraemos a los receiver de dicha campaña.
                $piezasIds = $fileCenti['piezas'][$campaings[$i]['target']['id']];
                $vultas = count($piezasIds);
                $idsReceivers = [];
                for ($p=0; $p < $vultas; $p++) {
                  $idsReceivers = array_merge($idsReceivers, $fileCenti['stt'][ $piezasIds[$p] ]['ctz']);
                }
                $idsReceivers = array_unique($idsReceivers);
                sort($idsReceivers);
                shuffle($idsReceivers);
                $campaings[$i]['receivers'] = $idsReceivers;
              }
            }

            $response['body'] = $campaings;
            $scm->clean($target);
          }else{
            $response['abort']= true;
            $response['msg']  = 'ERROR';
            $response['body'] = 'No se encontraron las ordenes ' . implode(',', $fileTargets[$target]);
          }
          break;
        default:
          # code...
    }

    return $this->json($response);
  }

  /**
   * Recuperamos los datos del contacto para almacenarlos en disco local.
   */
  #[Route('scm/get-contacto-byid/{idContac}/', methods:['get'])]
  public function getContactoById(NG2ContactosRepository $em, String $idContac): Response
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
