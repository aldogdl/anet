<?php

namespace App\Controller\Any;

use App\Service\Any\dto\MsgWs;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/yonke-mx')]
class YonkeMxWh extends AbstractController
{
	/** */
	#[Route('/wh', methods: ['get', 'post'])]
	public function webhookWa(Request $req): Response
	{
		if( $req->getMethod() == 'POST' ) {

				$msg = new MsgWs(json_decode($req->getContent(), true));
				if($msg->type == 'stt') { return new Response(200); }
				
				file_put_contents('wa_post_'.uniqid().'.json', $msg->toJson());

		} elseif( $req->getMethod() == 'GET' ) {

			$verify = $req->query->get('hub_verify_token');
			if($verify == $this->getParameter('anyToken')) {

				$mode = $req->query->get('hub_mode');
				if($mode == 'subscribe') {
					$challenge = $req->query->get('hub_challenge');
					file_put_contents('de_wa_get.json', json_encode([
						'mode' => $mode, 
						'verify' => $verify, 
						'challenge' => $challenge, 
					]));
					return new Response($challenge);
				}
			}
		}
		return new Response(400);
	}

	/** */
	#[Route('/test-com', methods: ['get'])]
	public function testCom(Request $req): Response
	{
			if( $req->getMethod() == 'GET' ) {
					return new Response(200);
			}
			return new Response(400);
	}

}
