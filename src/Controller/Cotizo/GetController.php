<?php

namespace App\Controller\Cotizo;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use App\Repository\AO1MarcasRepository;
use App\Repository\AO2ModelosRepository;
use App\Repository\NG2ContactosRepository;
use App\Repository\OrdenesRepository;
use App\Repository\OrdenPiezasRepository;
use App\Service\CentinelaService;
use App\Service\CotizaService;
use App\Service\StatusRutas;

class GetController extends AbstractController
{

    /** sin checar poder borrar */
    #[Route('cotizo/get-all-marcas/', methods:['get'])]
    public function getAllMarcas(AO1MarcasRepository $marcasEm): Response
    {
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $marcasEm->getAllAsArray()
        ]);
    }

    /** sin checar poder borrar */
    #[Route('cotizo/get-modelos-by-marca/{idMarca}/', methods:['get'])]
    public function getModelosByMarca(AO2ModelosRepository $modsEm, $idMarca): Response
    {
        $dql = $modsEm->getAllModelosByIdMarca($idMarca);
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $dql->getScalarResult()
        ]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/get-user-by-campo/', methods:['get'])]
    public function getUserByCampo(NG2ContactosRepository $contacsEm, Request $req): Response
    {
        $campo = $req->query->get('campo');
        $valor = $req->query->get('valor');
        $user = $contacsEm->findOneBy([$campo => $valor]);
        $result = [];
        $abort = true;
        if($user) {
            $abort = false;
            $result['u_id'] = $user->getId();
            $result['u_roles'] = $user->getRoles();
        }
        return $this->json(['abort'=>$abort, 'msg' => 'ok', 'body' => $result]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/is-tokenapz-caducado/', methods:['get'])]
    public function isTokenApzCaducado(): Response
    {
        return $this->json(['abort'=>false, 'msg' => 'ok', 'body' => ['nop' => 'nop']]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/get-ordenes-by-own-and-seccion/{idUser}/{est}/', methods:['get'])]
    public function getOrdenesByOwnAndEstacion(
        OrdenesRepository $ordenes, int $idUser, String $est
    ): Response
    {
        $dql = $ordenes->getOrdenesByOwnAndEstacion($idUser, $est);
        $ordens = $dql->getScalarResult();
        return $this->json(['abort' => false, 'body' => $ordens]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/get-piezas-by-lst-ordenes/{idsOrdenes}/', methods:['get'])]
    public function getPiezasByListOrdenes(
        OrdenPiezasRepository $piezas, String $idsOrdenes
    ): Response
    {
        $dql = $piezas->getPiezasByListOrdenes($idsOrdenes);
        $pzas = $dql->getScalarResult();
        return $this->json(['abort' => false, 'body' => $pzas]);
    }

    /** sin checar poder borrar */
    #[Route('cotizo/open-share-img-device/{filename}/', methods:['get'])]
    public function openShareImgDevice(CotizaService $cotService, String $filename): Response
    {
        $cotService->openShareImgDevice($filename);
        return $this->json(['abort' => false]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/check-share-img-device/{filename}/{tipo}/', methods:['get'])]
    public function checkShareImgDevice(CotizaService $cotService, String $filename, String $tipo): Response
    {
        $result = $cotService->checkShareImgDevice($filename, $tipo);
        return $this->json([
            'abort' => false, 'msg' => $tipo, 'body' => $result
        ]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/fin-share-img-device/{filename}/', methods:['get'])]
    public function finShareImgDevice(CotizaService $cotService, String $filename): Response
    {
        $result = $cotService->finShareImgDevice($filename);
        return $this->json([
            'abort' => false, 'body' => $result
        ]);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/del-share-img-device/{filename}/', methods:['get'])]
    public function delShareImgDevice(CotizaService $cotService, String $filename): Response
    {
        $cotService->delShareImgDevice($filename);
        return $this->json(['abort' => false, 'body' => '']);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/del-img-of-orden-tmp/{filename}/', methods:['get'])]
    public function removeImgOfOrdenToFolderTmp(CotizaService $cotService, String $filename): Response
    {
        $cotService->removeImgOfOrdenToFolderTmp($filename);
        return $this->json(['abort' => false, 'body' => '']);
    }

    /** sin checar poder borrar */
    #[Route('api/cotizo/del-pieza/{idPza}/', methods:['get'])]
    public function deletePiezaAntesDeSave(
        StatusRutas $rutas,
        CotizaService $cotService,
        OrdenesRepository $ordenEm,
        OrdenPiezasRepository $pzasEm,
        $idPza
    ): Response
    {
        $result = $pzasEm->deletePiezaAntesDeSave($idPza);
        if(!$result['abort']) {

            if(array_key_exists('fotos', $result['body'])) {

                $rota = count($result['body']['fotos']);
                for ($i=0; $i < $rota; $i++) {
                    $cotService->removeImgOfOrdenToFolderTmp($result['body']['fotos'][$i]);
                }
            }

            if(array_key_exists('orden', $result['body'])) {

                $piezasByOrden = $pzasEm->findBy(['orden' => $result['body']['orden']]);
                if(count($piezasByOrden) == 0) {
                    $stts = $rutas->getRutaByFilename($result['body']['ruta']);
                    $sttOrd = $rutas->getEstOrdenSinPiezas($stts);
                    $ordenEm->changeSttOrdenTo($result['body']['orden'], $sttOrd);
                }
                $result['body'] = [];
            }
        }
        return $this->json($result);
    }

}
