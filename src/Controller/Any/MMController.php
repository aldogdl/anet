<?php

namespace App\Controller\Any;

use App\Repository\MMEntityRepository;
use App\Service\SecurityBasic;
use App\Service\EventsWhVPSService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/any-mm')]
class MMController extends AbstractController
{

	/** */
	#[Route('/elements', methods: ['get', 'post', 'delete'])]
	public function mmElements(Request $req, MMEntityRepository $em): Response
	{
		if( $req->getMethod() == 'POST' ) {
			$data = $req->getContent();
			if($data) {
				return $this->json($em->setMM( json_decode($data, true) ));
			}
		} elseif( $req->getMethod() == 'DELETE' ) {
			$idAuto = $req->query->get('id');
			return $this->json($em->delMM( $idAuto ));
		} elseif( $req->getMethod() == 'GET' ) {
			$idMrk = $req->query->get('idMrk');
			return $this->json($em->getMM( $idMrk ));
		}
		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

	/** */
	#[Route('/mm-slim', methods: ['get'])]
	public function mmGetModelsSlim(Request $req, MMEntityRepository $em, ParameterBagInterface $params): Response
	{
		if( $req->getMethod() == 'GET' ) {
			$res = $em->getMMSlim();
			if (!$res['abort'] && $params->has('verapps')) {
				$vPath = Path::canonicalize($params->get('verapps'));
				if (file_exists($vPath)) {
					$vc = file_get_contents($vPath);
					if ($vc) {
						$vJson = json_decode($vc, true);
						if (isset($vJson['autos'])) {
							$res['data']['autosVersion'] = (string)$vJson['autos'];
						}
					}
				}
			}
			return $this->json($res);
		}
		return $this->json(['abort' => true, 'body' => 'Error inesperado']);
	}

	/**
	 * Guarda de manera atómica un lote de Marcas y Modelos (FASE 1).
	 *
	 * Protecciones:
	 * 1. Autenticación administrativa real: consulta servidor-servidor a ynksmx_auth
	 *    o valida token de servicio interno (sin exponer credenciales al cliente).
	 * 2. Idempotencia mediante operationId persistente.
	 * 3. Detección de concurrencia mediante baseVersion contra versions.json.
	 * 4. Transacción atómica Doctrine con rollback íntegro.
	 * 5. Versión monotónica estricta para 'autos' en versions.json preservando campos previos.
	 * 6. Disparo seguro de SSE hacia el VPS solo cuando el catálogo está versionado.
	 */
	#[Route('/save-draft-atomic', methods: ['POST'])]
	public function saveDraftAtomic(
		Request $req,
		MMEntityRepository $em,
		EventsWhVPSService $eventsWhVPS,
		HttpClientInterface $httpClient,
		ParameterBagInterface $params
	): Response {
		// 1. Extracción y validación de token administrativo
		$authHeader = (string)$req->headers->get('Authorization', '');
		$token = '';
		if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
			$token = $matches[1];
		}
		if (empty($token)) {
			$token = (string)$req->query->get('token', '');
		}

		$data = json_decode($req->getContent(), true);
		if (!is_array($data)) {
			return $this->json([
				'abort' => true,
				'code' => 'INVALID_JSON',
				'data' => 'El cuerpo de la solicitud debe ser un JSON válido.',
			], Response::HTTP_BAD_REQUEST);
		}

		if (empty($token) && !empty($data['token'])) {
			$token = (string)$data['token'];
		}

		$authCheck = $this->_validateAdminAuthorization($token, $httpClient, $params);
		if (!$authCheck['authorized']) {
			$status = match ($authCheck['code']) {
				'FORBIDDEN' => Response::HTTP_FORBIDDEN,
				'AUTH_SERVICE_UNAVAILABLE' => Response::HTTP_SERVICE_UNAVAILABLE,
				default => Response::HTTP_UNAUTHORIZED,
			};

			return $this->json([
				'abort' => true,
				'code' => $authCheck['code'],
				'data' => $authCheck['message'],
			], $status);
		}

		// 2. Preparación de rutas y bloqueo exclusivo de concurrencia
		$filesystem = new Filesystem();
		$scmDir = Path::canonicalize($params->get('scm'));
		$versionsPath = Path::canonicalize($params->get('verapps'));
		$recoveryPath = $scmDir . '/catalog_version_recovery.json';

		// Auto-recuperación durable previa si quedó un desfasaje pendiente
		$this->_recoverPendingVersionsFile($filesystem, $versionsPath, $recoveryPath);

		$lockFile = $scmDir . '/catalog_save.lock';
		if (!$filesystem->exists(dirname($lockFile))) {
			$filesystem->mkdir(dirname($lockFile), 0755);
		}
		$lockHandle = fopen($lockFile, 'c+');
		if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
			return $this->json([
				'abort' => true,
				'code' => 'LOCK_FAILED',
				'data' => 'No se pudo adquirir el bloqueo de concurrencia para el catálogo.',
			], Response::HTTP_SERVICE_UNAVAILABLE);
		}

		try {
			// A. Manejo de idempotencia bajo lock exclusivo
			$operationId = isset($data['operationId']) && is_string($data['operationId']) ? trim($data['operationId']) : '';
			$opCachePath = '';
			if ($operationId !== '') {
				$safeOpId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $operationId);
				$opCachePath = $scmDir . '/catalog_ops/op_' . $safeOpId . '.json';
				if ($filesystem->exists($opCachePath)) {
					$cachedContent = file_get_contents($opCachePath);
					if ($cachedContent) {
						$cachedJson = json_decode($cachedContent, true);
						if (is_array($cachedJson)) {
							return $this->json($cachedJson);
						}
					}
				}
			}

			// B. Verificación de concurrencia mediante baseVersion bajo lock exclusivo
			$baseVersion = isset($data['baseVersion']) && is_string($data['baseVersion']) ? trim($data['baseVersion']) : null;
			$versionsData = [];
			if ($filesystem->exists($versionsPath)) {
				$versionsContent = file_get_contents($versionsPath);
				if ($versionsContent) {
					$decoded = json_decode($versionsContent, true);
					if (is_array($decoded)) {
						$versionsData = $decoded;
					}
				}
			}

			$currentAutosVersion = isset($versionsData['autos']) ? (string)$versionsData['autos'] : null;
			if ($baseVersion !== null && (string)($currentAutosVersion ?? '') !== $baseVersion) {
				return $this->json([
					'abort' => true,
					'code' => 'CONCURRENCY_CONFLICT',
					'data' => "El catálogo fue actualizado por otro administrador (versión base: '$baseVersion', versión servidor: '" . ($currentAutosVersion ?? 'ninguna') . "'). Actualiza tu catálogo antes de guardar.",
				], Response::HTTP_CONFLICT);
			}

			// C. Ejecución del lote atómico en MySQL vía Doctrine
			$res = $em->saveDraftAtomic($data);
			if ($res['abort']) {
				$code = $res['code'] ?? 'ERROR';
				$status = ($code === 'DEPENDENCY_CONFLICT' || $code === 'DUPLICATE_NAME' || $code === 'COLLISION_CONFLICT' || $code === 'INVALID_CONSOLIDATION')
					? Response::HTTP_CONFLICT
					: Response::HTTP_BAD_REQUEST;
				return $this->json($res, $status);
			}

			// D. Actualización monotónica y recuperación durable de versions.json
			$numericCurrent = is_numeric($currentAutosVersion) ? (int)$currentAutosVersion : 0;
			$newAutosVersion = (string) max(time(), $numericCurrent + 1);
			$versionsData['autos'] = $newAutosVersion;

			// Registrar intención durable antes de escribir versions.json
			try {
				$filesystem->dumpFile(
					$recoveryPath,
					json_encode([
						'pendingAutosVersion' => $newAutosVersion,
						'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
					], JSON_UNESCAPED_SLASHES)
				);
			} catch (\Throwable $th) {
				error_log('No se pudo registrar archivo de recuperación durable: ' . $th->getMessage());
			}

			$versionSaved = false;
			try {
				$filesystem->dumpFile(
					$versionsPath,
					json_encode($versionsData, JSON_UNESCAPED_SLASHES)
				);
				$versionSaved = true;
				// Remueve recuperación una vez confirmado el archivo en disco
				if ($filesystem->exists($recoveryPath)) {
					$filesystem->remove($recoveryPath);
				}
			} catch (\Throwable $th) {
				$res['warning'] = 'MySQL persistido con éxito, pero falló la escritura del archivo versions.json. Se preservó registro de recuperación durable: ' . $th->getMessage();
			}

			$res['data']['autosVersion'] = $newAutosVersion;

			// E. Publicación SSE fire-and-forget al VPS
			if ($versionSaved) {
				try {
					$eventsWhVPS->send('versions_updated', 'update', [
						'scope' => 'autos',
						'version' => $newAutosVersion,
						'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
					]);
				} catch (\Throwable $th) {
					error_log('Error enviando webhook SSE versions_updated: ' . $th->getMessage());
				}
			}

			// F. Persistencia de caché de operación para idempotencia
			if ($operationId !== '' && $opCachePath !== '') {
				try {
					$filesystem->dumpFile($opCachePath, json_encode($res, JSON_UNESCAPED_SLASHES));
				} catch (\Throwable $th) {
					// No interrumpir la respuesta si falla la persistencia de idempotencia
				}
			}

			return $this->json($res);
		} finally {
			flock($lockHandle, LOCK_UN);
			fclose($lockHandle);
		}
	}

	/**
	 * Guardado atómico, concurrente e idempotente de dicc.json.
	 * 1. Autenticación administrativa Bearer / query / body mediante _validateAdminAuthorization.
	 * 2. Bloqueo exclusivo con flock sobre dictionary_save.lock en scmDir.
	 * 3. Idempotencia basada en operationId con caché de respuesta en catalog_ops/op_*.json.
	 * 4. Control de concurrencia optimista contra $currentDict['version'] con baseVersion (HTTP 409).
	 * 5. Validación de estructura y categorías canónicas sin eliminar campos existentes preexistentes.
	 * 6. Escritura atómica a disco vía Filesystem::dumpFile preservando version numérica monotónica.
	 * 7. Notificación push SSE al VPS con scope='dicc' vía EventsWhVPSService sin abortar ante fallos de red.
	 */
	#[Route('/save-dictionary-atomic', methods: ['POST'])]
	public function saveDictionaryAtomic(
		Request $req,
		EventsWhVPSService $eventsWhVPS,
		HttpClientInterface $httpClient,
		ParameterBagInterface $params
	): Response {
		// 1. Extracción y validación de token administrativo
		$authHeader = (string)$req->headers->get('Authorization', '');
		$token = '';
		if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
			$token = $matches[1];
		}
		if (empty($token)) {
			$token = (string)$req->query->get('token', '');
		}

		$data = json_decode($req->getContent(), true);
		if (!is_array($data)) {
			return $this->json([
				'abort' => true,
				'code' => 'INVALID_JSON',
				'data' => 'El cuerpo de la solicitud debe ser un JSON válido.',
			], Response::HTTP_BAD_REQUEST);
		}

		if (empty($token) && !empty($data['token'])) {
			$token = (string)$data['token'];
		}

		$authCheck = $this->_validateAdminAuthorization($token, $httpClient, $params);
		if (!$authCheck['authorized']) {
			$status = match ($authCheck['code']) {
				'FORBIDDEN' => Response::HTTP_FORBIDDEN,
				'AUTH_SERVICE_UNAVAILABLE' => Response::HTTP_SERVICE_UNAVAILABLE,
				default => Response::HTTP_UNAUTHORIZED,
			};

			return $this->json([
				'abort' => true,
				'code' => $authCheck['code'],
				'data' => $authCheck['message'],
			], $status);
		}

		// 2. Validación estructural básica del payload
		if (!isset($data['dictionary']) || !is_array($data['dictionary'])) {
			return $this->json([
				'abort' => true,
				'code' => 'INVALID_PAYLOAD',
				'data' => "El payload debe incluir un campo 'dictionary' de tipo objeto.",
			], Response::HTTP_BAD_REQUEST);
		}
		$incomingDict = $data['dictionary'];

		// 3. Preparación de rutas y bloqueo exclusivo de concurrencia
		$filesystem = new Filesystem();
		$scmDir = Path::canonicalize($params->get('scm'));
		$diccPath = $scmDir . '/dicc.json';
		$recoveryPath = $scmDir . '/dictionary_version_recovery.json';

		// Auto-recuperación durable previa si quedó un desfasaje pendiente
		$this->_recoverPendingDictionaryFile($filesystem, $diccPath, $recoveryPath);

		$lockFile = $scmDir . '/dictionary_save.lock';
		if (!$filesystem->exists(dirname($lockFile))) {
			$filesystem->mkdir(dirname($lockFile), 0755);
		}
		$lockHandle = fopen($lockFile, 'c+');
		if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
			return $this->json([
				'abort' => true,
				'code' => 'LOCK_FAILED',
				'data' => 'No se pudo adquirir el bloqueo de concurrencia para el diccionario.',
			], Response::HTTP_SERVICE_UNAVAILABLE);
		}

		try {
			// A. Manejo de idempotencia bajo lock exclusivo
			$operationId = isset($data['operationId']) && is_string($data['operationId']) ? trim($data['operationId']) : '';
			$opCachePath = '';
			if ($operationId !== '') {
				$safeOpId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $operationId);
				$opCachePath = $scmDir . '/catalog_ops/op_' . $safeOpId . '.json';
				if ($filesystem->exists($opCachePath)) {
					$cachedContent = file_get_contents($opCachePath);
					if ($cachedContent) {
						$cachedJson = json_decode($cachedContent, true);
						if (is_array($cachedJson)) {
							return $this->json($cachedJson);
						}
					}
				}
			}

			// B. Lectura del diccionario actual en disco
			$currentDict = [];
			if ($filesystem->exists($diccPath)) {
				$currentContent = file_get_contents($diccPath);
				if ($currentContent) {
					$decodedCurrent = json_decode($currentContent, true);
					if (is_array($decodedCurrent)) {
						$currentDict = $decodedCurrent;
					}
				}
			}

			// C. Verificación de concurrencia mediante baseVersion bajo lock exclusivo
			$currentVersion = isset($currentDict['version']) ? $currentDict['version'] : 1;
			$baseVersion = $data['baseVersion'] ?? null;
			if ($baseVersion !== null && (string)$baseVersion !== (string)$currentVersion) {
				return $this->json([
					'abort' => true,
					'code' => 'CONCURRENCY_CONFLICT',
					'data' => "El diccionario fue actualizado por otro administrador (versión base: '$baseVersion', versión servidor: '$currentVersion'). Actualiza tu diccionario antes de guardar.",
				], Response::HTTP_CONFLICT);
			}

			// D. Validación de estructura y categorías canónicas
			$listFields = ['basura', 'ruido', 'conectores_pieza', 'rasgos', 'rasgos_especiales', 'lados'];
			foreach ($listFields as $field) {
				if (isset($incomingDict[$field])) {
					if (!is_array($incomingDict[$field])) {
						return $this->json([
							'abort' => true,
							'code' => 'INVALID_FIELD_TYPE',
							'data' => "El campo '$field' debe ser un arreglo de cadenas.",
						], Response::HTTP_BAD_REQUEST);
					}
					$cleanedList = [];
					foreach ($incomingDict[$field] as $item) {
						if (!is_scalar($item)) {
							return $this->json([
								'abort' => true,
								'code' => 'INVALID_ITEM_TYPE',
								'data' => "Los elementos de '$field' deben ser cadenas de texto.",
							], Response::HTTP_BAD_REQUEST);
						}
						$s = trim((string)$item);
						if ($s !== '') {
							$cleanedList[] = $s;
						}
					}
					$incomingDict[$field] = array_values(array_unique($cleanedList));
				}
			}

			$mapFields = ['lado_conv', 'lp_encode', 'lp_decode', 'abreviaturas', 'reglas'];
			foreach ($mapFields as $field) {
				if (isset($incomingDict[$field])) {
					if (!is_array($incomingDict[$field])) {
						return $this->json([
							'abort' => true,
							'code' => 'INVALID_FIELD_TYPE',
							'data' => "El campo '$field' debe ser un mapa asociativo clave-valor.",
						], Response::HTTP_BAD_REQUEST);
					}
					$cleanedMap = [];
					foreach ($incomingDict[$field] as $k => $v) {
						if (!is_scalar($k) || !is_scalar($v)) {
							return $this->json([
								'abort' => true,
								'code' => 'INVALID_ITEM_TYPE',
								'data' => "Las claves y valores de '$field' deben ser texto.",
							], Response::HTTP_BAD_REQUEST);
						}
						$kStr = trim((string)$k);
						$vStr = trim((string)$v);
						if ($kStr !== '') {
							$cleanedMap[$kStr] = $vStr;
						}
					}
					$incomingDict[$field] = $cleanedMap;
				}
			}

			// E. Fusión preservando claves y campos existentes no remitidos
			$mergedDict = $currentDict;
			foreach ($incomingDict as $k => $v) {
				if ($k !== 'version') {
					$mergedDict[$k] = $v;
				}
			}

			// F. Cálculo de nueva versión numérica monotónica
			$numericCurrent = is_numeric($currentVersion) ? (int)$currentVersion : 0;
			$newDiccVersion = (int) max(time(), $numericCurrent + 1);
			$mergedDict['version'] = $newDiccVersion;

			// G. Intención durable previa
			try {
				$filesystem->dumpFile(
					$recoveryPath,
					json_encode([
						'pendingDiccVersion' => $newDiccVersion,
						'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
					], JSON_UNESCAPED_SLASHES)
				);
			} catch (\Throwable $th) {
				error_log('No se pudo registrar archivo de recuperación durable de diccionario: ' . $th->getMessage());
			}

			// H. Escritura atómica a disco (tmp + rename)
			try {
				$filesystem->dumpFile(
					$diccPath,
					json_encode($mergedDict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				);
				if ($filesystem->exists($recoveryPath)) {
					$filesystem->remove($recoveryPath);
				}
			} catch (\Throwable $th) {
				return $this->json([
					'abort' => true,
					'code' => 'WRITE_FAILED',
					'data' => 'Error al persistir dicc.json en disco: ' . $th->getMessage(),
				], Response::HTTP_INTERNAL_SERVER_ERROR);
			}

			// I. Disparo de webhook SSE fire-and-forget al VPS
			$webhookSuccess = false;
			$webhookWarning = null;
			try {
				$eventsWhVPS->send('versions_updated', 'update', [
					'scope' => 'dicc',
					'version' => $newDiccVersion,
					'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
					]);
				$webhookSuccess = true;
			} catch (\Throwable $th) {
				$webhookWarning = 'Diccionario guardado correctamente, pero falló la notificación push al VPS: ' . $th->getMessage();
				error_log($webhookWarning);
				try {
					$whFailedLog = $scmDir . '/webhook_failed_events.log';
					file_put_contents(
						$whFailedLog,
						date('c') . " event=versions_updated scope=dicc version=$newDiccVersion error=" . $th->getMessage() . "\n",
						FILE_APPEND
					);
				} catch (\Throwable $ignored) {
				}
			}

			// J. Respuesta e idempotencia
			$res = [
				'abort' => false,
				'data' => [
					'version' => $newDiccVersion,
					'webhookNotified' => $webhookSuccess,
				],
			];
			if ($webhookWarning !== null) {
				$res['warning'] = $webhookWarning;
			}

			if ($operationId !== '' && $opCachePath !== '') {
				try {
					$filesystem->dumpFile($opCachePath, json_encode($res, JSON_UNESCAPED_SLASHES));
				} catch (\Throwable $th) {
				}
			}

			return $this->json($res);
		} finally {
			flock($lockHandle, LOCK_UN);
			fclose($lockHandle);
		}
	}

	/**
	 * Auto-reconcilia dicc.json si existía un registro de recuperación durable pendiente.
	 */
	private function _recoverPendingDictionaryFile(Filesystem $filesystem, string $diccPath, string $recoveryPath): void
	{
		if (!$filesystem->exists($recoveryPath)) {
			return;
		}

		try {
			$recoveryContent = file_get_contents($recoveryPath);
			if (!$recoveryContent) {
				return;
			}
			$recoveryData = json_decode($recoveryContent, true);
			$pendingVersion = $recoveryData['pendingDiccVersion'] ?? null;
			if (!$pendingVersion) {
				return;
			}

			if ($filesystem->exists($diccPath)) {
				$currentDicc = json_decode(file_get_contents($diccPath), true);
				if (is_array($currentDicc) && ((int)($currentDicc['version'] ?? 0)) < (int)$pendingVersion) {
					$currentDicc['version'] = (int)$pendingVersion;
					$filesystem->dumpFile($diccPath, json_encode($currentDicc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				}
			}
			$filesystem->remove($recoveryPath);
		} catch (\Throwable $th) {
			error_log('Error en auto-recuperación de diccionario: ' . $th->getMessage());
		}
	}

	/**
	 * Auto-reconcilia versions.json si existía un registro de recuperación durable pendiente.
	 */
	private function _recoverPendingVersionsFile(Filesystem $filesystem, string $versionsPath, string $recoveryPath): void
	{
		if (!$filesystem->exists($recoveryPath)) {
			return;
		}

		try {
			$recoveryContent = file_get_contents($recoveryPath);
			if (!$recoveryContent) {
				return;
			}
			$recoveryData = json_decode($recoveryContent, true);
			$pendingAutos = $recoveryData['pendingAutosVersion'] ?? null;
			if (!$pendingAutos) {
				return;
			}

			$versionsData = [];
			if ($filesystem->exists($versionsPath)) {
				$vc = file_get_contents($versionsPath);
				if ($vc) {
					$versionsData = json_decode($vc, true) ?: [];
				}
			}

			$currentAutos = $versionsData['autos'] ?? '0';
			if ((int)$pendingAutos > (int)$currentAutos) {
				$versionsData['autos'] = (string)$pendingAutos;
				$filesystem->dumpFile(
					$versionsPath,
					json_encode($versionsData, JSON_UNESCAPED_SLASHES)
				);
			}

			$filesystem->remove($recoveryPath);
		} catch (\Throwable $th) {
			error_log('Error durante auto-recuperación de versions.json: ' . $th->getMessage());
		}
	}

	/**
	 * Valida la autorización administrativa antes de mutar el catálogo consultando
	 * servidor-servidor a ynksmx_auth (/admin/v1/auth/me). Requiere roles 'superadmin' o 'catalog'.
	 *
	 * @return array{authorized: bool, code: string, message: string, userId?: string, role?: string}
	 */
	private function _validateAdminAuthorization(
		string $token,
		HttpClientInterface $httpClient,
		ParameterBagInterface $params
	): array {
		if (empty($token)) {
			return [
				'authorized' => false,
				'code' => 'UNAUTHORIZED',
				'message' => 'Token de autorización ausente o inválido.',
			];
		}

		// 2. Verificación servidor-servidor de JWT administrativo contra ynksmx_auth
		$whUrl = $params->has('urlWhVPS') ? (string)$params->get('urlWhVPS') : 'https://yonkerosmx.com/anet/wh';
		$baseUrl = preg_replace('#/anet/wh/?$#i', '', $whUrl);
		if (empty($baseUrl)) {
			$baseUrl = 'https://yonkerosmx.com';
		}
		$authMeUrl = rtrim($baseUrl, '/') . '/admin/v1/auth/me';

		try {
			$response = $httpClient->request('GET', $authMeUrl, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept' => 'application/json',
				],
				'timeout' => 5.0,
			]);

			$statusCode = $response->getStatusCode();
			if ($statusCode === Response::HTTP_UNAUTHORIZED) {
				return [
					'authorized' => false,
					'code' => 'UNAUTHORIZED',
					'message' => 'JWT administrativo expirado o no reconocido por ynksmx_auth.',
				];
			}

			if ($statusCode !== Response::HTTP_OK) {
				return [
					'authorized' => false,
					'code' => 'UNAUTHORIZED',
					'message' => 'Validación de sesión administrativa rechazada (código HTTP ' . $statusCode . ').',
				];
			}

			$authBody = json_decode($response->getContent(false), true);
			if (!is_array($authBody)) {
				return [
					'authorized' => false,
					'code' => 'AUTH_SERVICE_UNAVAILABLE',
					'message' => 'Respuesta no interpretable desde ynksmx_auth.',
				];
			}

			$roleAdmin = strtolower(trim((string)($authBody['roleAdmin'] ?? '')));
			$allowedRoles = ['superadmin', 'catalog'];

			if (!in_array($roleAdmin, $allowedRoles, true)) {
				return [
					'authorized' => false,
					'code' => 'FORBIDDEN',
					'message' => 'El rol administrativo (' . ($roleAdmin ?: 'desconocido') . ') no tiene permisos para modificar el catálogo vehicular.',
				];
			}

			return [
				'authorized' => true,
				'code' => 'OK',
				'message' => 'Autorizado vía JWT administrativo.',
				'userId' => (string)($authBody['userId'] ?? ''),
				'role' => $roleAdmin,
			];
		} catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
			return [
				'authorized' => false,
				'code' => 'AUTH_SERVICE_UNAVAILABLE',
				'message' => 'VPS de autenticación ynksmx_auth inaccesible: ' . $e->getMessage(),
			];
		} catch (\Throwable $th) {
			return [
				'authorized' => false,
				'code' => 'AUTH_SERVICE_UNAVAILABLE',
				'message' => 'Fallo al consultar ynksmx_auth: ' . $th->getMessage(),
			];
		}
	}


	/**
	 * Prevalida de solo lectura la eliminación o consolidación de una marca o modelo.
	 * Retorna conteo real de piezas en item_pub, viabilidad de consolidación,
	 * modelos trasladados y colisiones detectadas contrastando con el borrador.
	 */
	#[Route('/prevalidate-deletion', methods: ['POST'])]
	public function prevalidateDeletion(
		Request $req,
		MMEntityRepository $em,
		HttpClientInterface $httpClient,
		ParameterBagInterface $params
	): Response {
		// 1. Extracción y validación de token administrativo
		$authHeader = (string)$req->headers->get('Authorization', '');
		$token = '';
		if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
			$token = $matches[1];
		}
		if (empty($token)) {
			$token = (string)$req->query->get('token', '');
		}

		$data = json_decode($req->getContent(), true);
		if (!is_array($data)) {
			return $this->json([
				'abort' => true,
				'code' => 'INVALID_JSON',
				'data' => 'El cuerpo de la solicitud debe ser un JSON válido.',
			], Response::HTTP_BAD_REQUEST);
		}

		if (empty($token) && !empty($data['token'])) {
			$token = (string)$data['token'];
		}

		$authCheck = $this->_validateAdminAuthorization($token, $httpClient, $params);
		if (!$authCheck['authorized']) {
			$status = match ($authCheck['code']) {
				'FORBIDDEN' => Response::HTTP_FORBIDDEN,
				'AUTH_SERVICE_UNAVAILABLE' => Response::HTTP_SERVICE_UNAVAILABLE,
				default => Response::HTTP_UNAUTHORIZED,
			};

			return $this->json([
				'abort' => true,
				'code' => $authCheck['code'],
				'data' => $authCheck['message'],
			], $status);
		}

		$res = $em->previewDeletionOrConsolidation($data);
		return $this->json($res);
	}

}
