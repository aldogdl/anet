<?php

namespace App\Controller\SCP;

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
        if($data['empresaId'] != 1) {
            $result = $empEm->seveDataContact($data['empresa']);
            if(!$result['abort']) {
                $data['contacto']['empresaId'] = $result['body']; 
                $result = $contactsEm->seveDataContact($data['contacto']);
            }
        }else{
            $result = $contactsEm->seveDataContact($data);
        }
        return $this->json($result);
    }

    #[Route('scp/delete-contacto/{idContac}/', methods:['get'])]
    public function deleteContacto(
        NG2ContactosRepository $contactsEm,
        int $idContac,
    ): Response
    {   

        $result = $contactsEm->borrarContactoById($idContac);
        return $this->json($result);
    }

}
