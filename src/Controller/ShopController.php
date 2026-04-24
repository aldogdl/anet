<?php

namespace App\Controller;

use App\Repository\ItemPubRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


class ShopController extends AbstractController
{
	#[Route('/{slug}', name: 'app_shop', methods: ['GET'], priority: -1)]
	public function index(
		string $slug, 
		Request $request, 
		ItemPubRepository $itemRepo
	): Response {

		// Evitar que slugs comunes o archivos estáticos activen la tienda
		if (in_array($slug, ['api', 'admin', 'login', 'logout', '_profiler', '_wdt', 'favicon.ico'])) {
			throw $this->createNotFoundException('Ruta no válida para tienda.');
		}

		$page = $request->query->getInt('page', 1);
		$search = $request->query->get('q', '');
		$limit = 12;

		// Aquí buscaríamos los productos del usuario por su slug.
		// Por ahora, usamos el repositorio para traer los items asociados a ese slug.
		// Nota: En un futuro aquí se integraría la lógica de MeLi si fuera necesario.

		$queryBuilder = $itemRepo->createQueryBuilder('i')
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
				$mock->setTitle("Faro Delantero LED - Modelo Premium " . $i);
				$mock->setPrice(1250.00 + ($i * 100));
				$mock->setThumb("https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&q=80&w=600");
				$mock->setIku("REF-00" . $i);
				$mock->setDetalles("Esta es una pieza de alta calidad, garantizada para un ajuste perfecto en modelos 2018-2023. Resistente al agua y con certificación premium.");
				$mock->setMrkId(1);
				$mock->setMdlId(10);
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
	#[Route('/api/filters/{slug}', name: 'api_shop_filters', methods: ['GET'])]
	public function getFilters(string $slug, ItemPubRepository $itemRepo): Response
	{
		// Lógica para obtener marcas y modelos de forma agrupada
		// Esto se cargará vía AJAX para no ralentizar el render inicial
		$items = $itemRepo->findBy(['slug' => $slug, 'isActive' => true]);
		
		$filters = [];
		foreach ($items as $item) {
			$mrkId = $item->getMrkId();
			$mdlId = $item->getMdlId();
			// Aquí idealmente tendrías los nombres de marcas/modelos, 
			// pero si solo tienes IDs, podrías mockear o buscar en otra entidad.
			$filters[$mrkId]['name'] = "Marca " . $mrkId; 
			$filters[$mrkId]['models'][$mdlId] = "Modelo " . $mdlId;
		}

		return $this->json($filters);
	}
}
