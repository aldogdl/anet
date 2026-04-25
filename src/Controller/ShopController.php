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
			// Codificación simple (base64 + rot13) para enviar al frontend sin exponerlo directamente
			$encodedMeliToken = base64_encode(str_rot13($meliLog['token']));
		}

		$page = $request->query->getInt('page', 1);
		$search = $request->query->get('q', '');
		$mrkId = $request->query->get('mk', '');
		$mdlId = $request->query->get('md', '');
		$limit = 12;

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
			'encodedMeliToken' => $encodedMeliToken
		]);
	}

	#[Route('/search/{template}/{slug}', name: 'app_shop_search', methods: ['GET'], requirements: ['template' => 'muro|shop'])]
	public function search(string $template, string $slug, Request $request, ItemPubRepository $repo): Response
	{
		$page = $request->query->getInt('page', 1);
		$search = $request->query->get('q', '');
		$mrkId = $request->query->get('mk', '');
		$mdlId = $request->query->get('md', '');
		$limit = 12;

		$encodedMeliToken = $request->query->get('mt', '');
		$meliToken = '';
		if ($encodedMeliToken) {
			// Decodificamos el token si viene en la petición para usarlo en búsquedas MeLi
			$meliToken = str_rot13(base64_decode($encodedMeliToken));
		}

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
		// MOCK DATA
		if ($totalItems === 0 || $slug === 'demo') {
			$items = [];
			for ($i = 1; $i <= 8; $i++) {
				$mock = new \App\Entity\ItemPub();
				$mock->setTitle("Parrilla Atlas O Terramont 2024-2025 " . $i);
				$mock->setPrice(45400.00 + ($i * 100));
				$mock->setThumb("https://autoparnet.com/inv/images/yunkeonline/Obf0ATaa6tyc/Obf0ATaa6tyc_1.jpg");
				$mock->setIku("REF-ABC-" . $i);
				$mock->setDetalles("Parrilla frontal para Volkswagen Atlas o Terramont 2024. Calidad original certificada.");
				$mock->setMrkId(1);
				$mock->setMdlId(10);
				$mock->setExtras([
					'pathImg' => 'https://autoparnet.com/inv/images/yunkeonline/Obf0ATaa6tyc',
					'pictures' => ['Obf0ATaa6tyc_1.jpg', 'Obf0ATaa6tyc_2.jpg']
				]);
				$mock->setIsActive(true);
				$items[] = $mock;
			}
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
		$templateFile = $viewFolder . '/_product_grid.html.twig';

		return $this->render($templateFile, [
			'slug' => $slug,
			'template' => $template,
			'items' => $items,
			'currentPage' => $page,
			'pagesCount' => $pagesCount,
			'search' => $search,
			'storeName' => ucfirst($slug),
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
			$extras = $item->getExtras();
			$mrkName = $extras['mk'] ?? 'Marca Desconocida';
			$mdlName = $extras['md'] ?? 'Modelo Desconocido';

			if (!isset($brandMap[$mrkId])) {
				$brandMap[$mrkId] = [
					'id' => $mrkId,
					'name' => strtoupper($mrkName),
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
