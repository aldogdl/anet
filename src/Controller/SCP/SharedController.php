<?php

namespace App\Controller\SCP;

use App\Entity\NG1Empresas;
use App\Entity\NG2Contactos;
use App\Repository\NG1EmpresasRepository;
use App\Repository\NG2ContactosRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SharedController extends AbstractController
{
    
    #[Route('scp/get-all-contactos-by/{tipo}/', methods:['get'])]
    public function getAllContactsBy(
        NG2ContactosRepository $contactsEm,
        string $tipo
    ): Response
    {   
        $dql = $contactsEm->getAllContactsBy($tipo);
        return $this->json([
            'abort'=>false, 'msg' => 'ok',
            'body' => $dql->getScalarResult()
        ]);
    }

    #[Route('scp/seve-data-contact/', methods:['post'])]
    public function seveDataContact(
        Request $req,
        NG1EmpresasRepository $empEm,
        NG2ContactosRepository $contactsEm,
    ): Response
    {   
        $data = json_decode( $req->request->get('data'), true );
        $result = $empEm->seveDataContact($data['empresa']);
        if(!$result['abort']) {
            $data['contacto']['empresaId'] = $result['body']; 
            $result = $contactsEm->seveDataContact($data['contacto']);
        }
        return $this->json($result);
    }

    #[Route('scp/delete-contacto/{idContac}/', methods:['get'])]
    public function deleteContacto(
        NG1EmpresasRepository $empEm,
        NG2ContactosRepository $contactsEm,
        int $idContac,
    ): Response
    {   

        $result = ['abort' => false, 'msg' => 'ok', 'body' => 'ok'];
        $ct = $contactsEm->find(NG2Contactos::class, $idContac);

        if($ct) {
            $emp = $empEm->find(NG1Empresas::class, $ct->getEmpresa()->getId());

            if($emp) {
                try {
                    $contactsEm->remove($ct);
                    $empEm->remove($emp);
                } catch (\Throwable $th) {
                    $result = [
                        'abort' => true,
                        'msg'   => $th->getMessage(),
                        'body'  => 'Error al borrar contacto, inténtalo nuevamente'
                    ];
                }
            }else{
                $result = [
                    'abort' => true,
                    'msg'   => 'err',
                    'body'  => 'No se encontró la empresa con el ID ' . $ct->getEmpresa()->getId()
                ];
            }
        }else{
            $result = [
                'abort' => true,
                'msg'   => 'err',
                'body'  => 'No se encontró el contacto con el ID ' . $idContac
            ];
        }
        return $this->json($result);
    }

}
