<?php

namespace App\Controller\Mlm;

use App\Service\DataSimpleMlm;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class MlmController extends AbstractController
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

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/notifications/', methods: ['GET', 'POST'])]
    public function notisMlm(Request $req): Response
    {
        file_put_contents('mlm_wh_'.time().'.json', json_encode($req->getContent()));
        return new Response('listo MLM');
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/code/', methods: ['GET', 'POST'])]
    public function verifyMlm(Request $req): Response
    {
        $slug = $req->query->get('state');
        $code = $req->query->get('code');
        if(mb_strlen($code) > 10) {
            file_put_contents('mlm_'.$slug.'.txt', $code);
            return new Response(file_get_contents('shop/mlm_exito.html'));
        }
        return new Response('Bienvenido a ANY->MLM', 200);
    }

    /**
     * Endpoint para la verificacion de conección
     */
    #[Route('mlm/set-token-fromapp/{slug}', methods: ['POST'])]
    public function setNewTokenMlmFromApp(Request $req, DataSimpleMlm $mlm, String $slug): Response
    {
        $data = [];
        try {
            $data = $this->toArray($req, 'data');
        } catch (\Throwable $th) {
            $data = $req->getContent();
            if($data) {
                $data = json_decode($data, true);
            }else{
                return $this->json(['body' => ['error' => 'X No se logró decodificar correctamente los datos de la request.']]);
            }
        }
        
        if(count($data) > 0) {
            if(array_key_exists('slug', $data)) {
                $res = $mlm->setCodeTokenMlm($data, $data['slug']);
                if(array_key_exists('token', $res)) {
                    return $this->json(['body' => ['result' => 'ok']]);
                }
            }
        }
        return $this->json(['body' => ['error' => 'X Error no controlado']]);
    }

    /**
     * Al vincular mlm con anyShop se crea un json con los datos de dicha
     * vinculacion por lo tanto se recuperan desde la app AnyShop y se
     * eliminan inmediatamente.
     */
    #[Route('mlm/parse-cot-token/{slug}/', methods: ['GET'])]
    public function mlmParseCodeToken(Request $req, DataSimpleMlm $mlm, String $slug): Response
    {
        if($req->getMethod() == 'GET') {

            $path = 'mlm_'.$slug.'.txt';
            if(!is_file($path)) {
                return $this->json(['abort' => false, 'body' => ['error' => 'X Aun no llega']]);
            }

            try {
                $code = file_get_contents($path);
                if($code) {
                    $isOk = $mlm->parseCodeToToken($code, $slug);
                    if(count($isOk) > 0) {
                        unlink($path);
                        return $this->json($isOk);
                    }
                }
                return $this->json(['abort' => true, 'body' => ['error' => 'X Error en los datos']]);
            } catch (\Throwable $th) {
                return $this->json(['abort' => true, 'body' => ['error' => 'X ' . $th->getMessage()]]);
            }
        }
        
        return $this->json(['abort' => true, 'body' => ['error' => 'X Error desconocido']]);
    }

}
