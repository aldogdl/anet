<?php

namespace App\Controller;

use App\Repository\ItemPubRepository;
use App\Service\Any\Fsys\AnyPath;
use App\Service\Any\Fsys\Fsys;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * ShopController
*/
class ShopController extends AbstractController
{

	#[Route('/{template}/{slug}', name: 'app_shop', methods: ['GET'], requirements: ['template' => 'muro|shop'])]
	public function index(string $template, string $slug, Request $request, ItemPubRepository $repo, Fsys $fsys): Response
	{
		// Leemos los archivos solo en la carga inicial
		$dtaCtc = $fsys->get(AnyPath::$DTACTC, $slug.'.json');
		$meliLog = $fsys->get(AnyPath::$DTACTCLOG, $slug.'.json');
		
		$hasMeli = false;
		$encodedMeliToken = '';
		
		if (is_array($meliLog) && !empty($meliLog['token']) && !empty($meliLog['refreshTk'])) {
			$hasMeli = true;
			// Codificación simple (base64 + rot13) incluyendo el userId
			$userId = $meliLog['userId'] ?? '';
			$encodedMeliToken = base64_encode(str_rot13($userId . '|' . $meliLog['token']));
		}

		$contactPhone = '';
		if (!empty($dtaCtc['colabs']) && is_array($dtaCtc['colabs'])) {
			foreach ($dtaCtc['colabs'] as $colab) {
				if (isset($colab['roles']) && is_array($colab['roles']) && in_array('ROLE_MAIN', $colab['roles'])) {
					if (!empty($colab['waId'])) {
						$contactPhone = $colab['waId'];
						break;
					}
				}
			}
		}

		$page = $request->query->getInt('page', 1);
		$search = $request->query->get('q', '');
		$mrkId = $request->query->get('mk', '');
		$mdlId = $request->query->get('md', '');
		$limit = 16;

		// Query para obtener las piezas del usuario por slug
		$queryBuilder = $repo->createQueryBuilder('i')
			->where('i.slug = :slug')
			->andWhere('i.isActive = :active')
			->setParameter('slug', $slug)
			->setParameter('active', true)
			->orderBy('i.created', 'DESC');

		if ($search) {
			$queryBuilder->andWhere('i.title LIKE :search OR i.detalles LIKE :search')
				->setParameter('search', '%' . $search . '%');
		}

		if ($mrkId) {
			$queryBuilder->andWhere('i.mrkId = :mrkId')
				->setParameter('mrkId', $mrkId);
		}

		if ($mdlId) {
			$queryBuilder->andWhere('i.mdlId = :mdlId')
				->setParameter('mdlId', $mdlId);
		}

		// Paginación manual simple
		$totalItems = count($queryBuilder->getQuery()->getResult());
		// MOCK DATA para demostración si no hay resultados o es el slug 'demo'
		if ($totalItems === 0) {
			$items = [];
			$totalItems = 0;
			$pagesCount = 1;
		} else {
			$pagesCount = ceil($totalItems / $limit);
			$items = $queryBuilder
				->setFirstResult(($page - 1) * $limit)
				->setMaxResults($limit)
				->getQuery()
				->getResult();
		}

		$viewFolder = $template === 'muro' ? 'vistas/muro' : 'vistas/shop';
		$templateFile = $viewFolder . '/index.html.twig';

		return $this->render($templateFile, [
			'slug' => $slug,
			'template' => $template,
			'items' => $items,
			'currentPage' => $page,
			'pagesCount' => $pagesCount,
			'search' => $search,
			'storeName' => empty($dtaCtc) ? ucfirst($slug) : $dtaCtc['empresa'], 
			'dtaCtc' => $dtaCtc,
			'hasMeli' => $hasMeli,
			'encodedMeliToken' => $encodedMeliToken,
			'contactPhone' => $contactPhone
		]);
	}

	#[Route('/search/{template}/{slug}', name: 'app_shop_search', methods: ['GET'], requirements: ['template' => 'muro|shop'])]
	public function search(string $template, string $slug, Request $request, ItemPubRepository $repo): Response
	{
		$page = $request->query->getInt('page', 1);
		$search = trim($request->query->get('q', ''));
		$mrkId = $request->query->get('mk', '');
		$mdlId = $request->query->get('md', '');
		$limit = 16;

		$encodedMeliToken = $request->query->get('mt', '');
		$missingIds = [];
		$items = [];
		$totalItems = 0;

		// Base query para BD local
		$queryBuilder = $repo->createQueryBuilder('i')
			->where('i.slug = :slug')
			->andWhere('i.isActive = :active')
			->setParameter('slug', $slug)
			->setParameter('active', true);

		// Si hay búsqueda con texto y tenemos token de ML, ML actúa como motor principal
		if ($search && $encodedMeliToken) {
			$decoded = str_rot13(base64_decode($encodedMeliToken));
			$parts = explode('|', $decoded, 2);
			if (count($parts) === 2) {
				[$userId, $token] = $parts;
				$opts = [
					'http' => [
						'method' => 'GET',
						'header' => "Authorization: Bearer $token\r\n"
					]
				];
				$context = stream_context_create($opts);
				$urlIds = "https://api.mercadolibre.com/users/{$userId}/items/search?q=" . urlencode($search) . "&status=active&limit=50";
				$json = @file_get_contents($urlIds, false, $context);
				

				if ($json) {
					$data = json_decode($json, true);
					$results = $data['results'] ?? [];
					
					if (!empty($results)) {
						// Extraer de la BD local usando los IDs encontrados
						$dbItems = $queryBuilder
							->andWhere('i.idSrc IN (:ids)')
							->setParameter('ids', $results)
							->getQuery()
							->getResult();
						
						// Preservar el orden de relevancia entregado por MeLi
						$dbItemsMap = [];
						$existingIds = [];
						foreach ($dbItems as $item) {
							$dbItemsMap[$item->getIdSrc()] = $item;
							$existingIds[] = $item->getIdSrc();
						}
						
						foreach ($results as $idSrc) {
							if (isset($dbItemsMap[$idSrc])) {
								$items[] = $dbItemsMap[$idSrc];
							}
						}
						
						// Faltantes para que el frontend los recupere con otro AJAX
						$missingIds = array_diff($results, $existingIds);
						$totalItems = count($items);
					}
				}
			}
		}

		// Si no se encontraron items por MeLi, o no es una búsqueda con texto, usamos búsqueda estándar de BD
		if (empty($items) && empty($missingIds)) {
			$queryBuilder = $repo->createQueryBuilder('i')
				->where('i.slug = :slug')
				->andWhere('i.isActive = :active')
				->setParameter('slug', $slug)
				->setParameter('active', true)
				->orderBy('i.created', 'DESC');

			if ($search) {
				$queryBuilder->andWhere('i.title LIKE :search OR i.detalles LIKE :search')
					->setParameter('search', '%' . $search . '%');
			}
			if ($mrkId) {
				$queryBuilder->andWhere('i.mrkId = :mrkId')
					->setParameter('mrkId', $mrkId);
			}
			if ($mdlId) {
				$queryBuilder->andWhere('i.mdlId = :mdlId')
					->setParameter('mdlId', $mdlId);
			}

			$totalItems = count($queryBuilder->getQuery()->getResult());
			$items = $queryBuilder
				->setFirstResult(($page - 1) * $limit)
				->setMaxResults($limit)
				->getQuery()
				->getResult();
		}

		$pagesCount = ceil($totalItems / $limit);
		if ($pagesCount == 0) $pagesCount = 1;

		$viewFolder = $template === 'muro' ? 'vistas/muro' : 'vistas/shop';
		$templateFile = $viewFolder . '/_product_grid.html.twig';

		return $this->render($templateFile, [
			'slug' => $slug,
			'template' => $template,
			'items' => $items,
			'currentPage' => $page,
			'pagesCount' => $pagesCount,
			'search' => $search,
			'storeName' => ucfirst($slug),
			'missingIds' => implode(',', array_slice($missingIds, 0, 20))
		]);
	}

	#[Route('/search/meli/{slug}', name: 'app_shop_search_meli', methods: ['GET'])]
	public function searchMeli(string $slug, Request $request, ItemPubRepository $repo): Response
	{
		$idsStr = $request->query->get('ids', '');
		if (empty(trim($idsStr))) return new Response('');

		$encodedMeliToken = $request->query->get('mt', '');
		if (!$encodedMeliToken) return new Response('');

		$decoded = str_rot13(base64_decode($encodedMeliToken));
		$parts = explode('|', $decoded, 2);
		if (count($parts) !== 2) return new Response('');
		
		[$userId, $token] = $parts;

		$opts = [
			'http' => [
				'method' => 'GET',
				'header' => "Authorization: Bearer $token\r\n"
			]
		];
		$context = stream_context_create($opts);

		// Obtener detalles de los IDs faltantes y formatear
		$urlItems = "https://api.mercadolibre.com/items?ids={$idsStr}&attributes=id,title,price,thumbnail,pictures,permalink,attributes";
		$jsonItems = @file_get_contents($urlItems, false, $context);
		if (!$jsonItems) return new Response('');

		$itemsData = json_decode($jsonItems, true);
		$items = [];

		foreach ($itemsData as $itemWrapper) {
			if ($itemWrapper['code'] !== 200) continue;
			$meliItem = $itemWrapper['body'];
			
			$mock = new \App\Entity\ItemPub();
			// Asignamos un ID 0 de manera transparente
			$reflection = new \ReflectionClass($mock);
			$property = $reflection->getProperty('id');
			$property->setValue($mock, 0);

			$mock->setIdSrc($meliItem['id']);
			$mock->setTitle($meliItem['title']);
			$mock->setPrice((float) $meliItem['price']);
			
			$thumb = $meliItem['thumbnail'];
			if (!empty($meliItem['pictures'])) {
				$thumb = $meliItem['pictures'][0]['secure_url'] ?? $meliItem['pictures'][0]['url'] ?? $thumb;
			}
			$mock->setThumb($thumb);
			$mock->setSlug($slug);
			$mock->setIsActive(true);
			$mock->setIku($meliItem['id']); 
			
			$mrkName = '';
			$mdlName = '';
			if (!empty($meliItem['attributes'])) {
				foreach ($meliItem['attributes'] as $attr) {
					if ($attr['id'] === 'BRAND') $mrkName = $attr['value_name'];
					if ($attr['id'] === 'MODEL') $mdlName = $attr['value_name'];
				}
			}
			$mock->setExtras([
				'mk' => $mrkName,
				'md' => $mdlName,
				'permalink' => $meliItem['permalink'],
				'pictures' => [] 
			]);

			$items[] = $mock;
		}

		if (empty($items)) return new Response('');

		return $this->render('vistas/shop/_meli_results.html.twig', [
			'items' => $items,
			'slug' => $slug
		]);
	}

	#[Route('/pieza/{id}-{slug}', name: 'app_product_detail', methods: ['GET'])]
	public function productDetail(int $id, string $slug, ItemPubRepository $itemRepo): Response
	{
		$item = $itemRepo->find($id);
		if (!$item) {
			throw $this->createNotFoundException('La pieza no fue encontrada.');
		}

		// Creamos una página básica por ahora
		return $this->render('vistas/shop/product_detail.html.twig', [
			'item' => $item,
			'storeName' => 'Autoparnet'
		]);
	}

	/** */
	#[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
	public function addToCart(int $id, SessionInterface $session, ItemPubRepository $itemRepo): Response
	{
		$cart = $session->get('cart', []);
		
		if (!isset($cart[$id])) {
			$item = $itemRepo->find($id);
			if ($item) {
				$cart[$id] = [
					'id' => $item->getId(),
					'title' => $item->getTitle(),
					'price' => $item->getPrice(),
					'thumb' => $item->getThumb(),
					'quantity' => 1
				];
			}
		} else {
			$cart[$id]['quantity']++;
		}

		$session->set('cart', $cart);
		return $this->json(['success' => true, 'cartCount' => count($cart)]);
	}

	/** */
	#[Route('/filters/{slug}', name: 'app_shop_filters', methods: ['GET'])]
	public function getFilters(string $slug, ItemPubRepository $itemRepo, \Doctrine\ORM\EntityManagerInterface $em): Response
	{
		// Modo Demo enriquecido
		if ($slug === 'demo') {
			return $this->json([
				['id' => 1, 'name' => 'VOLKSWAGEN', 'count' => 12, 'models' => [
					['id' => 10, 'name' => 'Terramont', 'count' => 5],
					['id' => 11, 'name' => 'Jetta', 'count' => 7],
					['id' => 12, 'name' => 'Taos', 'count' => 2],
				]],
				['id' => 2, 'name' => 'NISSAN', 'count' => 8, 'models' => [
					['id' => 20, 'name' => 'Versa', 'count' => 4],
					['id' => 21, 'name' => 'Sentra', 'count' => 4],
				]],
				['id' => 3, 'name' => 'FORD', 'count' => 5, 'models' => [
					['id' => 30, 'name' => 'F-150', 'count' => 3],
					['id' => 31, 'name' => 'Explorer', 'count' => 2],
				]],
				['id' => 4, 'name' => 'CHEVROLET', 'count' => 15, 'models' => [
					['id' => 40, 'name' => 'Aveo', 'count' => 10],
					['id' => 41, 'name' => 'Silverado', 'count' => 5],
				]],
			]);
		}

		$items = $itemRepo->findBy(['slug' => $slug, 'isActive' => true]);
		
		// Agrupar por marcas y modelos
		$brandMap = [];
		foreach ($items as $item) {

			$mrkId = $item->getMrkId() ?? 0;
			$mdlId = $item->getMdlId() ?? 0;
			
			// Intentar obtener nombres de extras si existen
			$extras = $item->getExtras() ?? [];
			$mrkName = $extras['mk'] ?? 'Marca Desconocida';
			if (is_array($mrkName)) {
				$mrkName = !empty($mrkName) ? reset($mrkName) : 'Marca Desconocida';
			}
			$mdlName = $extras['md'] ?? 'Modelo Desconocido';
			if (is_array($mdlName)) {
				$mdlName = !empty($mdlName) ? reset($mdlName) : 'Modelo Desconocido';
			}

			if (!isset($brandMap[$mrkId])) {
				$brandMap[$mrkId] = [
					'id' => $mrkId,
					'name' => strtoupper((string) $mrkName),
					'count' => 0,
					'models' => []
				];
			}
			$brandMap[$mrkId]['count']++;

			if (!isset($brandMap[$mrkId]['models'][$mdlId])) {
				$brandMap[$mrkId]['models'][$mdlId] = [
					'id' => $mdlId,
					'name' => $mdlName,
					'count' => 0
				];
			}
			$brandMap[$mrkId]['models'][$mdlId]['count']++;
		}

		// Convertir a array plano para el JSON
		$result = array_values(array_map(function($b) {
			$b['models'] = array_values($b['models']);
			return $b;
		}, $brandMap));

		return $this->json($result);
	}

}
