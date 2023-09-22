<?php

namespace App\Controller\BackCore;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class GetController extends AbstractController
{
    /** */
    #[Route('back-core/conv-free/{waid}/', methods: ['GET', 'DELETE'])]
    public function putCotInConvFree(Request $req, String $waid): Response
    {
        if($req->getMethod() == 'GET') {
            $filename = 'conv_free.'.$waid.'.cnv';
            file_put_contents($filename, '');
            return $this->json(['code' => $filename]);
        }

        if($req->getMethod() == 'DELETE') {

            $filename = 'conv_free.'.$waid.'.cnv';
            if(is_file($filename)) {
                unlink($filename);
                return $this->json(['code' => 'exit']);
            }
        }

        return $this->json(['code' => 'error']);
    }
}
