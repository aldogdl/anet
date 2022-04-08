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
        $result = $empEm->seveDataContact($data['empresa']);
        if(!$result['abort']) {
            $data['contacto']['empresaId'] = $result['body']; 
            $result = $contactsEm->seveDataContact($data['contacto']);
        }
        return $this->json($result);
    }

}
