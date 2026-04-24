<?php

namespace App\Controller;

use App\Repository\ItemPubRepository;
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
	#[Route('/{slug}', name: 'app_shop', methods: ['GET'], priority: -1)]
	public function index(string $slug, Request $request, ItemPubRepository $repo): Response
	{
		// Evitar que slugs comunes o archivos estáticos activen la tienda
		$noEntrarSi = ['api', 'admin', 'login', 'logout', '_profiler', '_wdt', 'favicon.ico'];
		if (in_array($slug, $noEntrarSi)) {
			throw $this->createNotFoundException('Ruta no válida para tienda.');
		}

		$page = $request->query->getInt('page', 1);
		$search = $request->query->get('q', '');
		$limit = 12;

		// Aquí buscaríamos los productos del usuario por su slug.
		// Por ahora, usamos el repositorio para traer los items asociados a ese slug.
		// Nota: En un futuro aquí se integraría la lógica de MeLi si fuera necesario.
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

		// Paginación manual simple
		$totalItems = count($queryBuilder->getQuery()->getResult());
		// MOCK DATA para demostración si no hay resultados o es el slug 'demo'
		if ($totalItems === 0 || $slug === 'demo') {
			$items = [];
			for ($i = 1; $i <= 8; $i++) {
				$mock = new \App\Entity\ItemPub();
				$mock->setTitle("Parrilla Atlas O Terramont 2024-2025 " . $i);
				$mock->setPrice(45400.00 + ($i * 100));
				$mock->setThumb("https://autoparnet.com/inv/images/yunkeonline/Obf0ATaa6tyc/Obf0ATaa6tyc_1.jpg");
				$mock->setIku("REF-ABC-" . $i);
				$mock->setDetalles("Parrilla frontal para Volkswagen Atlas o Terramont 2024. Calidad original certificada.");
				$mock->setMrkId(1); // Marca 1
				$mock->setMdlId(10);
				$mock->setExtras([
					'pathImg' => 'https://autoparnet.com/inv/images/yunkeonline/Obf0ATaa6tyc',
					'pictures' => ['Obf0ATaa6tyc_1.jpg', 'Obf0ATaa6tyc_2.jpg']
				]);
				$mock->setIsActive(true);
				$items[] = $mock;
			}
			$totalItems = count($items);
			$pagesCount = 1;
		} else {
			$pagesCount = ceil($totalItems / $limit);
			$items = $queryBuilder
				->setFirstResult(($page - 1) * $limit)
				->setMaxResults($limit)
				->getQuery()
				->getResult();
		}

		return $this->render('shop/index.html.twig', [
			'slug' => $slug,
			'items' => $items,
			'currentPage' => $page,
			'pagesCount' => $pagesCount,
			'search' => $search,
			'storeName' => ucfirst($slug), // Placeholder para el nombre de la tienda
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
	#[Route('/muro/filters/{slug}', name: 'muro_shop_filters', methods: ['GET'])]
	public function getFilters(string $slug, ItemPubRepository $itemRepo, \Doctrine\ORM\EntityManagerInterface $em): Response
	{
		// Modo Demo
		if ($slug === 'demo') {
			return $this->json([
				['id' => 1, 'name' => 'VOLKSWAGEN', 'count' => 12, 'models' => [
					['id' => 10, 'name' => 'Terramont', 'count' => 5],
					['id' => 11, 'name' => 'Jetta', 'count' => 7],
				]],
				['id' => 2, 'name' => 'NISSAN', 'count' => 8, 'models' => [
					['id' => 20, 'name' => 'Versa', 'count' => 4],
					['id' => 21, 'name' => 'Sentra', 'count' => 4],
				]],
				['id' => 3, 'name' => 'FORD', 'count' => 5, 'models' => []],
				['id' => 4, 'name' => 'CHEVROLET', 'count' => 15, 'models' => []],
			]);
		}

		$items = $itemRepo->findBy(['slug' => $slug, 'isActive' => true]);
		$brandIds = array_unique(array_map(fn($i) => $i->getMrkId(), $items));
		
		if (empty($brandIds)) {
			return $this->json([]);
		}

		// Obtener nombres de marcas
		$brands = $em->getRepository(\App\Entity\MMEntity::class)->findBy(['id' => $brandIds]);
		
		$filters = [];
		foreach ($brands as $brand) {
			$filters[] = [
				'id' => $brand->getId(),
				'name' => $brand->getName(),
				'count' => count(array_filter($items, fn($i) => $i->getMrkId() === $brand->getId())),
				'models' => [] // Aquí podrías implementar la lógica real de modelos si fuera necesario
			];
		}

		return $this->json($filters);
	}
}
