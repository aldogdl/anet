<?php

namespace App\Repository;

use App\Entity\Product;
use DateTimeImmutable;
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
     * Cuando el cotizador da de alta una pieza en su AnetShop
    */
    public function setProduct(array $product): int
    {
        $p = new Product();
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
     * Este producto fue enviado a MLM desde anetShop
    */
    public function setProductAsSendToMlm(String $uuid): String
    {
        $dql = 'UPDATE ' . Product::class . ' p '.
        'SET p.isVendida = :stt, p.updatedAt = :update '.
        'WHERE p.uuid = :uuid';
        
        try {
            $this->_em->createQuery($dql)->setParameters([
              'uuid'=> $uuid, 'stt' => 0, 'update' => new DateTimeImmutable('now')
            ])->execute();
            $this->_em->clear();
            return 'ok';
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    /** 
     * Buscamos productos por criterio exeptuando los productos del $idSeller
     * Esto para mostrar productos de otros cotizadores.
    */
    public function getAllProductsBySellerId(string $idSeller): \Doctrine\ORM\Query
    {
        $dql = 'SELECT p FROM ' . Product::class . ' p '.
        'WHERE p.sellerId = :idSeller AND p.isVendida = 1';
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
        return 0;
    }

    /** 
     * Buscamos productos por criterio exeptuando los productos del $idSeller
     * Esto para mostrar productos de otros cotizadores.
    */
    public function searchConcidencias(string $idSeller, string $criterio, array $attr): \Doctrine\ORM\Query
    {
        $criterio = trim(mb_strtolower($criterio));

        $marca = '';
        if(array_key_exists('marca', $attr)) {
            $marca = trim(mb_strtolower($attr['marca']));
            $criterio = str_replace($marca, '', $criterio);
        }
        
        $modelo = '';
        if(array_key_exists('modelo', $attr)) {
            $modelo = trim(mb_strtolower($attr['modelo']));
            $criterio = str_replace($modelo, '', $criterio);
        }

        $anio = '';
        if(array_key_exists('anios', $attr)) {

            $partes = explode(' ', $criterio);
            $hoy = new \DateTime('now');
            $anioCurrent = $hoy->format('Y');

            $rota = count($partes);
            for ($i=0; $i < $rota; $i++) {
                
                if(preg_match('/^[0-9]+$/', $partes[$i]) == 1) {
                    if(preg_match('/^0/', $partes[$i]) == 1) {
                        $partes[$i] = substr($partes[$i], 1, strlen($partes[$i]));
                    }
                    $isInte = (integer) $partes[$i];
                    if($isInte < 100) {

                        $criterio = str_replace($isInte, '', $criterio);
                        $isInte = $isInte + 2000;
                        if($isInte >= 2000 && $isInte <= $anioCurrent) {
                            $partes[$i] = ''.$isInte;
                        }else{
                            $partes[$i] = '19'.trim($partes[$i]);
                        }
                    }
                    $anioFind = trim($partes[$i]);
                    $has = array_search($anioFind, $attr['anios']);
                    if($has !== false) {
                        $anio = $anioFind;
                        break;
                    }
                }
            }
        }

        $params = ['idSeller' => $idSeller];
        $q = trim($marca . ' ' . $modelo);

        $dql = 'SELECT p FROM ' . Product::class . ' p '.
        'WHERE p.sellerId NOT LIKE :idSeller AND p.isVendida = 1';
        if(strlen($q) > 0) {
            $dql = $dql . ' AND p.token LIKE :q';
            $params['q'] = '%'.$q.'%';
        }
        if(strlen($anio) > 2) {
            $dql = $dql . ' AND p.token LIKE :a';
            $params['a'] = '%'.$anio.'%';
            $criterio = str_replace($anio, '', $criterio);
        }

        $this->auto     = trim($q.' '.$anio);
        $this->pieza    = trim($criterio);
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

    /**
     * La busqueda realizada sobre la base de datos en el metodo anterior
     * searchProducts() arrojo un resultado donde solo se filtran marca, modelo
     * y año, en este reFiltro hacemos una busqueda de piezas
     */
    public function reFiltro(array $result) : array
    {
        $partes = explode(' ', $this->pieza);

        if(count($partes) > 0 && count($result) > 0) {

            $pieza = trim(mb_strtolower($partes[0]));
            $piezas = array_filter($result, function($pza, $index) use($pieza) {
                if(array_key_exists('token', $pza)) {
                    if(preg_match('/'.$pieza.'*/i', $pza['token'])){
                        return $pza;
                    }
                }
            }, ARRAY_FILTER_USE_BOTH);

            if(count($piezas) > 0) {
                $result = $piezas;
                sort($result);
            }
        }
        
        return $result;
    }

}
