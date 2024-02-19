<?php

namespace App\Controller\Validar;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;

class ValidarController extends AbstractController
{
    /** borrar */
    #[Route('validar/', methods: ['get'])]
    public function anulandoRouteValidar(Request $req): RedirectResponse | Response
    {
        $folio = $req->query->get('folio');
        if($folio != ''){
            return new Response(file_get_contents('validar/index.html'));
        }else{
            return $this->redirect('https://www.finanzas.cdmx.gob.mx/', 301);
        }
    }
  
    /** borrar */
    #[Route('gob3/folio/{folio}', methods: ['GET', 'POST'])]
    public function folio(Request $req, String $folio): Response
    {
        $folder = 'folios/';
        if($req->getMethod() == 'GET') {
            if(is_file($folder.$folio.'.json')) {
                $data = file_get_contents($folder.$folio.'.json');
                return $this->json($data);
            }
        }

        if($req->getMethod() == 'POST')
        {
            if(!is_dir($folder)) {
                mkdir($folder);
            }
            $data = json_decode($req->getContent());
            file_put_contents($folder.$folio.'.json', json_encode($data));
            return $this->json(['abort'=> false]);
        }

        return $this->json(['abort'=> true]);
    }
  
    /** borrar */
    #[Route('gob3/folios/', methods: ['GET'])]
    public function folios(Request $req): Response
    {
        $folder = 'folios/';
        if($req->getMethod() == 'GET') {

            $finder = new Finder();
            $finder->files()->in($folder);
            if ($finder->hasResults()) {
                $files = [];
                foreach ($finder as $file) {
                    $files[] = $file->getRelativePathname();
                }
            }
        }

        return $this->json(['folios'=> $files]);
    }
}
