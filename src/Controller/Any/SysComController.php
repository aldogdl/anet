<?php

namespace App\Controller\Any;

use App\Entity\UsCom;
use App\Repository\ItemPubRepository;
use App\Repository\SyncMlRepository;
use App\Repository\UsComRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use App\Service\Any\GetDataShop;
use App\Service\Any\PublicAssetUrlGenerator;
use App\Service\Pushes;
use App\Service\SecurityBasic;
use Kreait\Firebase\Messaging\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;

#[Route('/sys-com')]
class SysComController extends AbstractController
{

	/** **/
	#[Route('/init-app-mob', methods: ['post'])]
	public function initAppMob(Request $req, Fsys $fsys): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}

		$data = json_decode($data, true);
		if(!array_key_exists('slug', $data)) {
			return $this->json(['abort' => true, 'body' => 'Faltan el slug del usuario'], 403);
		}

		$waId = '_otro_dev';
		if(array_key_exists('waId', $data)) {
			$waId = '_'.$data['waId'];
		}

		// Creamos una marca de presencia o uso de la app
		$path = $fsys->buildPath(AnyPath::$SYNCDEV, 'devs+'.$data['slug'].$waId.'.json');
		$date = new \DateTime('now');
		// Timestamp en segundos
		$timestampSeconds = $date->getTimestamp();
		// Timestamp en milisegundos
		$timestampMilliseconds = $timestampSeconds * 1000;
		$data['last'] = $timestampMilliseconds;
		$fsys->setByPath($path, $data);
		
		// Recuperamos los datos de any shoper
		$path = $fsys->buildPath(AnyPath::$SYNCDEV, 'local+'.$data['slug'].'.json');
		$data = $fsys->getByPath($path);
		return $this->json(($data) ? $data : ['error' => 'No se encontraron datos']);
	}

	/** 
	 * 
	*/
	#[Route('/centinela', methods: ['post'])]
	public function centinela(
		Request $req, Fsys $fsys, SyncMlRepository $emMl, ItemPubRepository $emPub
	): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}

		$data = json_decode($data, true);
		$slug = '';
		if(!array_key_exists('slug', $data)) {
			return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
		}

		$slug = $data['slug'];
		$waId = 'otra_cuenta';
		if(array_key_exists('waId', $data)) {
			$waId = $data['waId'];
		}

		$verDicc = 1;
		if(array_key_exists('dicc_sync', $data)) {
			$verDicc = (integer) $data['dicc_sync'];
		}

		// Creamos una marca de presencia o uso de la app
		$path = $fsys->buildPath(AnyPath::$SYNCDEV, 'presence+'.$data['slug'].'_'.$waId.'.json');
		$date = new \DateTime('now');
		// Timestamp en segundos
		$timestampSeconds = $date->getTimestamp();
		// Timestamp en milisegundos
		$timestampMilliseconds = $timestampSeconds * 1000;
		$fsys->setByPath($path, ['last' => $timestampMilliseconds]);

		$ctcLog = $fsys->get(AnyPath::$DTACTC, $data['slug'].'.json');
		if(array_key_exists('filenames', $data)) {
			$lista = $data['filenames'];
			$rota = count($lista);
			$files = [];
			for ($i=0; $i < $rota; $i++) {
				$partes = explode('/', $lista[$i]);
				$partes = explode('.', $partes[1]);
				$field = $partes[0];
				if($field == 'dicc') {
					$dicc = $fsys->getByPath($lista[$i]);
					if(array_key_exists('version', $dicc) && $dicc['version'] > $verDicc) {
						$files[$field] = $dicc;
					}
				} else {
					$files[$field] = $fsys->getByPath($lista[$i]);
				}
			}
		}

		if(array_key_exists('last', $data)) {

			$last = $data['last'];
			// Recuperamos notificaciones de MeLi
			if(array_key_exists('meli', $last) && array_key_exists('idUserMl', $data)) {
				$meli = $emMl->getAllMsgAfterByMsgId($data['idUserMl'], $last['meli']);
				$files['meli'] = $meli;
			}
			// Recuperamos actualizaciones de Match1
			if(array_key_exists('pub', $last)) {
				$pubs = $emPub->getAllMsgAfterUpdate($slug, $last['pub']);
				$files['pubs'] = $pubs;
			}
		}

		$files['ctc'] = $ctcLog;
		return $this->json($files);
	}

	/** 
	 * 
	*/
	#[Route('/get-pendings', methods: ['post'])]
	public function getPendings(Request $req, ItemPubRepository $emPub): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}

		$data = json_decode($data, true);
		$slug = '';
		if(!array_key_exists('slug', $data)) {
			return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
		}
		$slug = $data['slug'];
		$ids = $data['ids'] ?? [];

		$pubs = $emPub->getAllItemsByIds($slug, $ids);
		return $this->json($pubs);
	}

	/** 
	 * 
	*/
	#[Route('/set-file-json/{token}', methods: ['post'])]
	public function setFileJson(Request $req, Fsys $fsys, SecurityBasic $security, String $token): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}
		if(mb_strpos($token, 'test::') !== false) {
			$partes = explode('::', $token);
			$token = base64_encode($partes[1]);
		}
		if(!$security->isValid($token)) {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}
		
		$data = json_decode($data, true);
		if(!array_key_exists('waId', $data)) {
			return $this->json(['abort' => true, 'body' => 'Falta el WaId del Usuario'], 402);
		}
		if(!array_key_exists('topic', $data)) {
			return $this->json(['abort' => true, 'body' => 'Falta el topico del Archivo'], 402);
		}
		if(!array_key_exists('body', $data)) {
			return $this->json(['abort' => true, 'body' => 'Falta el body'], 402);
		}
		$time = (integer) microtime(true) * 1000;
		$filename = $data['waId']."_".$time;
		// Si es local no se agrega el timestamp
		if($data['topic'] == 'local') {
			$filename = $data['slug'];
		}
		$path = $fsys->buildPath(AnyPath::$SYNCDEV, $data['topic'] .'+'.$filename.'.json');
		$path = $fsys->setByPath($path, $data);

		return $this->json(['file' => $path]);
	}

	/** */
	#[Route('/test', methods: ['get'])]
	public function test(Request $req, PublicAssetUrlGenerator $urlGen): Response
	{
		$prodSols = $this->getParameter(AnyPath::$PRODSOLS);
		$originalFilename = $req->query->get('file');
		$path = Path::canonicalize($prodSols.'/'.$originalFilename);

		if (!file_exists($path)) {
			return $this->json(['abort' => true, 'body' => 'X No existe archivo' . $path], 402);
		}else{
			$url = $urlGen->generate($path);
			return $this->json(['abort' => true, 'body' => 'Ok:' . $url], 200);
		}
		return new Response(400);
	}

	/** Datos para any shop */
	#[Route('/get-data-any', methods: ['post'])]
	public function getDataAnyShop(Request $req, GetDataShop $shop): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}

		$data = json_decode($data, true);
		if(!array_key_exists('slug', $data) || !array_key_exists('dev', $data)) {
			return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
		}

		$res = $shop->getSimpleData($data);
		return $this->json($res);
	}

	/** */
	#[Route('/update-data-com', methods: ['post'])]
	public function updateDataCom(Request $req, UsComRepository $em): Response
	{
		if( $req->getMethod() == 'POST' ) {
			$data = $req->getContent();
			if(!$data) {
				return new Response(403);
			}

			$data = json_decode($data, true);
			if(array_key_exists('dev', $data)) {
				$obj = new UsCom();
				$obj->fromJson($data);
				$res = $em->updateDataCom($obj);
				return $this->json(['abort' => false, 'body' => $res]);
			}else{
				return $this->json(['abort' => true, 'body' => 'X Faltaron datos, Inténtalo nuevamente']);
			}
		}
		return new Response(400);
	}

	/** 
	 * Si el cliente falla en enviar desde el FRM la notif a core, este mismo
	 * hace reintentos para que core este enterado del nuevo item
	*/
	#[Route('/push-core', methods: ['post'])]
	public function sendPushToCore(Request $req, UsComRepository $em, Pushes $push): Response 
	{
		$data = json_decode($req->getContent(), true);
		if(array_key_exists('code', $data)) {

			$how = file_get_contents($this->getParameter('report'));
			$token = $em->getTokenByWaId($how);
			$notif = Notification::create('Refuerzo de Solicitud', $data['code'], '');
			$result = $push->sendTo($token, $notif, ['ownApp' => $data['slugApp']]);
			if(array_key_exists('sended', $result)) {
				return $this->json(['abort' => false, 'id' => $result['sended']['name']]);
			}
		}

		return $this->json([]);
	}

	/** Desde el core subimos los datos de com-int */
	#[Route('/set-comloc', methods: ['post'])]
	public function setComLoc(Request $req): Response 
	{
		if($req->getMethod() == 'POST') {
			$header = $req->headers->get('any-token') ?? '';
			if($header == $this->getParameter('getAnToken')) {
				$data = $req->getContent();
				if($data) {
					$scm = $this->getParameter(AnyPath::$COMMLOC);
					file_put_contents(Path::canonicalize($scm), $data);
					return $this->json(['abort' => false]);
				}
			}
		}
		return $this->json(['abort' => true]);
	}

	/** */
	#[Route('/dta-ctc-list', methods: ['GET'])]
	public function listarArchivos(Request $req): Response
	{
		$carpeta = $this->getParameter('dtaCtc');

		if (!is_dir($carpeta)) {
			mkdir($carpeta, 0777, true);
		}

		$indexFile = "index_dta_ctc.json";

		if (file_exists($indexFile)) {
			// Si el index ya existe, lo leemos
			$contenido = file_get_contents($indexFile);
			$archivos = json_decode($contenido, true);
		} else {

			// Si no existe, lo generamos
			$archivos = [];

			foreach (scandir($carpeta) as $archivo) {
				if (
					is_file("$carpeta/$archivo") &&
					pathinfo($archivo, PATHINFO_EXTENSION) === 'json'
				) {
					$archivos[] = [
						'ctc' => $archivo,
						'modificado' => date('c', filemtime("$carpeta/$archivo")),
					];
				}
			}

			// Guardamos el índice generado
			file_put_contents($indexFile, json_encode($archivos));
		}

		return $this->json([
			'status' => 'ok',
			'archivos' => $archivos,
		]);
	}

	/** Datos para vinc con ml */
	#[Route('/get-data-ownml', methods: ['post'])]
	public function getDataOwnMl(Request $req, Fsys $fsys): Response
	{
		if($req->getMethod() != 'POST') {
			return $this->json(['body' => 'Ok, gracias'], 400);
		}

		$data = $req->getContent();
		if(!$data) {
			return $this->json(['abort' => true, 'body' => 'No se recibió contenido'], 402);
		}

		$data = json_decode($data, true);
		if(!array_key_exists('slug', $data)) {
			return $this->json(['abort' => true, 'body' => 'Faltan datos de recuperacion'], 403);
		}

		$ctcLog = $fsys->get(AnyPath::$DTACTCLOG, $data['slug'].'.json');
		$apiml = $fsys->get(AnyPath::$ANYMLM, '');
		return $this->json([
			'ctcLog' => $ctcLog,
			'apiml' => $apiml,
		]);
	}

	/** */
	#[Route('/update-meli', methods: ['POST'])]
	public function updateDataMeli(Request $req): Response
	{
		$data = $req->getContent();
		if($data) {
			$map = json_decode($data, true);
			if(array_key_exists('slug', $map)) {
				$logs = $this->getParameter('dtaCtcLog');
				$path = Path::canonicalize($logs.'/'.$map['slug'].'.json');
				file_put_contents($path, json_encode($map));
				return $this->json(['abort' => false]);
			}
		}
		return $this->json(['abort' => true]);
	}

	/** 
	 * Validamos que el slug de la empresa este entre las registradas
	*/
	#[Route('/validate-nickname', methods: ['HEAD'])]
	public function validateNick(Request $req): Response
	{
		$data = $req->headers->get('x-nickname');
		if($data) {
			$exp = $this->getParameter('dtaCtc');
			$path = Path::canonicalize($exp.'/'.$data.'.json');

			$exists = file_exists($path);
			return new Response(
				'',
				$exists ? 200 : 404,
				['X-Nickname-Valid' => $exists ? '1' : '0']
			);
		}
		return new Response('', 403, ['X-Nickname-Valid' => '0']);
	}
  
	/** 
	 * Validamos que el slug de la empresa este entre las registradas
	*/
	#[Route('/reg-auth', methods: ['GET'])]
	public function autorizarRegistro(Request $req, Fsys $fsys): Response
	{

	 	if($req->getMethod() != 'GET') {
			return $this->json(['msg' => 'El método no es válido'], 200);
		}
		
		$slug = '';
		$socio = '';
		$token = '';
		$query = $req->query->all();

		if(!array_key_exists('slug', $query)) {
			return $this->json(['msg' => 'El slug no se recibió'], 200);
		}
		if(!array_key_exists('me', $query)) {
			return $this->json(['msg' => 'El me no se recibió'], 200);
		}

		$slug = $query['slug'];
		$who = $query['me'];
		if(mb_strpos($who, '.') === false) {
			return $this->json(['msg' => 'El formato de me es inválido'], 200);
		}

		$partes = explode('.', $who);
		$socio = $partes[0];
		$token = $partes[1];

    $content = ['msg' => 'ok'];
		if($slug == '') {
			$content = ['msg' => 'Tu nombre de usuario no se recibió'];
		}
		if($socio == '') {
			$content = ['msg' => 'El nombre del Administrador no se recibió'];
		}
		if($token == '') {
			$content = ['msg' => 'El token del Administrador no se recibió'];
		}
		
		if($content['msg'] != 'ok') {
			return $this->json($content, 200);
		}
		
		$auth = $fsys->get(AnyPath::$DTACTC, $slug.'.json');
		if($auth && array_key_exists('colabs', $auth)) {
			return $this->json(['msg' => 'Usuario existente'], 200);
		}

		$auth = $fsys->get(AnyPath::$DTACTC, $socio.'.json');
		if($auth && array_key_exists('colabs', $auth)) {

			// buscar el colaborador que tenga el rol ROLE_MAIN y conservar su JSON
			$mainColab = null;
			foreach ($auth['colabs'] as $colab) {
				if (isset($colab['roles']) && in_array('ROLE_MAIN', $colab['roles'], true)) {
					$mainColab = $colab;
					break;
				}
			}

			if (!$mainColab) {
				$content = ['msg' => 'No existe ningún Administrador Autorizado'];
			} elseif (!isset($mainColab['pass']) || $mainColab['pass'] !== $token) {
				// el token no coincide con la contraseña del colaborador principal
				$content = ['msg' => 'Token inválido para el Administrador principal'];
			} else {
				$content = ['msg' => 'ok'];
			}

			if($content['msg'] != 'ok') {
				return $this->json($content, 200);
			}

			$content['asesor'] = $socio;
			$res = $fsys->set(AnyPath::$REGAUTH, $content, $slug.'.json');
			if($res == '') {
				return $this->json(['msg' => 'Registro Autorizado'], 200);
			}else{
				return $this->json(['abort' => true, 'msg' => $res], 200);
			}
		}

		return $this->json(['abort' => true, 'msg' => 'Acceso denegado'], 200);
	}

	/** 
	 * Validamos que el slug de la empresa este entre las registradas
	*/
	#[Route('/update-data-user', methods: ['POST'])]
	public function updateDataUser(Request $req, Fsys $fsys): Response
	{
		$data = $req->getContent();
		if($data) {

			$data = json_decode($data, true);
			if(array_key_exists('slug', $data)) {

				if(array_key_exists('registro', $data)) {

					$reg = $fsys->get(AnyPath::$REGAUTH, $data['slug'].'.json');
					$res = $fsys->del(AnyPath::$REGAUTH, $data['slug'].'.json');
					unset($data['registro']);
					if($res && array_key_exists('asesor', $reg)) {
						$data['asesor'] = $reg['asesor'];
					}
				}

				$res = $fsys->set(AnyPath::$DTACTC, $data, $data['slug'].'.json');
				if($res == '') {
					return $this->json(['abort' => false]);
				}else{
					return $this->json(['abort' => true, 'erro' => $res], 400);
				}
			}
		}
		return $this->json(['abort' => true], 403);
	}

}
