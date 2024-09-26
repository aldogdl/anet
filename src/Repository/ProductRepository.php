<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public $auto  = '';
    public $pieza = '';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** 
     * Eliminamos la pieza
    */
    public function delete(String $idPza): String
    {
        $p = $this->_em->find(Product::class, $idPza);
        if($p) {
            $p->setIsVendida(5);
            try {
                $this->_em->persist($p);
                $this->_em->flush();
                return 'ok';
            } catch (\Throwable $th) {
                return 'Error en SR, no se eliminó';
            }
        }
    }

    /** 
     * Cuando el cotizador da de alta o edita una pieza en su AnetShop
    */
    public function setProduct(array $product): int
    {
        if($product['id'] != -1) {
            $p = $this->_em->find(Product::class, $product['id']);
        }else{
            $p = new Product();
        }
        
        $p->fromMap($product);
        try {
            $this->_em->persist($p);
            $this->_em->flush();
            return $p->getId();
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /** 
     * Este producto fue enviado a MLM desde anetShop por lo tanto lo marcamos
     * como suspendido (stt = 0).
    */
    public function updateStatusProduct(array $data): String
    {
        if(array_key_exists('id', $data)) {
            $obj = $this->_em->find(Product::class, $data['id']);
            if($obj) {

                if(array_key_exists('price', $data)) {
                    $precio = $obj->getPrice();
                    $obj->setPrice($data['price']);
                    $obj->setOriginalPrice($precio);
                }
                if(array_key_exists('idMlm', $data)) {
                    $attrs = $obj->getAttrs();
                    $attrs['sku'] = $data['idMlm'];
                    $obj->setAttrs($attrs);
                }
                
                $obj->setIsVendida($data['stt']);
                $obj->setUpdatedAt(new \DateTimeImmutable('now'));

                $this->_em->persist($obj);
                $this->_em->flush();
                return 'ok';
            }
        }
        return 'No se Guardo el cambio';
    }

    /** 
     * Recuperamos todos los productos del id seller
    */
    public function getAllProductsBySellerId(string $idSeller): \Doctrine\ORM\Query
    {
        $dql = 'SELECT p FROM ' . Product::class . ' p '.
        'WHERE p.sellerId = :idSeller AND p.isVendida < 2';
        return $this->_em->createQuery($dql)->setParameter('idSeller', $idSeller);
    }

    /** 
     * Cuantificamos la cantidad de productos activos que tiene el cotizador 
    */
    public function getCount(string $idSeller): int
    {
        $dql = 'SELECT COUNT(p.id) FROM ' . Product::class . ' p '.
        'WHERE p.sellerId = :idSeller AND p.isVendida = 1';
        $result = $this->_em->createQuery($dql)
            ->setParameter('idSeller', $idSeller)->getResult(Query::HYDRATE_SINGLE_SCALAR);
        return $result;
    }

    /** 
     * Buscamos productos por criterio exeptuando los productos del $idSeller
     * Esto para mostrar productos de otros cotizadores.
    */
    public function searchReferencias(string $idSeller, array $attr): \Doctrine\ORM\Query
    {
        $params = ['idSeller' => $idSeller];

        $dql = 'SELECT p FROM ' . Product::class . ' p '.
        'WHERE p.sellerId NOT LIKE :idSeller AND p.isVendida < 2 ';
        
        // Reducir la busqueda con productos que comiencen con la refaccion
        $refa = $attr['pza'];
        $partes = explode(' ', $refa);
        $refa = $partes[0];
        $dql = $dql . 'AND p.token LIKE :pza ';
        $params['pza'] = '%'.$refa.'%';

        $mdl = $attr['modelo'];
        $partes = explode(' ', $mdl);
        $mdl = $partes[0];
        $dql = $dql . 'AND p.token LIKE :mdl';
        $params['mdl'] = '%'.$mdl.'%';

        return $this->_em->createQuery($dql)->setParameters($params);
    }

    ///
    public function paginador(\Doctrine\ORM\Query $query, int $page = 1, $mode = 'array', int $limit = 50): array
    {
        if($mode == 'array') {
            $query->setHydrationMode(Query::HYDRATE_ARRAY);
        }else{
            $query->setHydrationMode(Query::HYDRATE_SCALAR);
        }

        $query = $query->setFirstResult($limit * ($page - 1))->setMaxResults($limit);
        
        $pag = new Paginator($query);
        $totalItems = $pag->count();
        $pagesCount = ceil($totalItems / $limit);
        $results = [];
        foreach ($pag as $pzas) {
			$results[] = $pzas;
		}
        return [
            'paging' => [
                'total' => $totalItems,
                'pages' => $pagesCount,
                'offset'=> $page,
                'primary_results' => count($results),
                'limit' => $limit
            ],
            'query'  => [
                'auto' => $this->auto,
                'pieza' => $this->pieza,
            ],
            'result' => $results
        ];
    }

}
