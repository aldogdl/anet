<?php

namespace App\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/** */
class PaginatorQuery
{
    ///
    public function pagine(
        \Doctrine\ORM\Query $query, int $limit = 50, String $showAs = 'normal', int $page = 1, String $mode = 'array'
    ): array
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
        foreach ($pag as $item) {
			$results[] = $this->showAs($item, $showAs);
		}
        return [
            'paging' => [
                'total' => $totalItems,
                'pages' => $pagesCount,
                'offset'=> $page,
                'results' => count($results),
                'limit' => $limit
            ],
            'result' => $results
        ];
    }

    ///
    private function showAs(array $item, String $show): array {

        switch ($show) {
            case 'min':
                return $this->showMin($item);
            default:
                return $item;
        }
    }

    ///
    private function showMin(array $item): array {

        $token = $item['pieza'];
        if(array_key_exists('lado', $item) && $item['lado'] != '') {
            $token = $token.' '.$item['lado'];
        }
        if(array_key_exists('poss', $item) && $item['poss'] != '') {
            $token = $token.' '.$item['poss'];
        }
        if(array_key_exists('marca', $item) && $item['marca'] != '') {
            $token = $token.' '.$item['marca'];
        }
        if(array_key_exists('model', $item) && $item['model'] != '') {
            $token = $token.' '.$item['model'];
        }
        if(array_key_exists('anios', $item)) {
            if(count($item['anios']) > 1) {
                if(count($item['anios']) > 2) {
                    $token = $token.' '.implode(', ', $item['anios']);
                }else{
                    $token = $token.' '.implode('-', $item['anios']);
                }
            }else{
                $token = $token.' '.$item['anios'][0];
            }
        }
        
        $fecha = $item['createdAt'];
        return [
            'id'        => $item['id'],
            'item'      => $token,
            'ownWaId'   => $item['ownWaId'],
            'ownSlug'   => $item['ownSlug'],
            'stt'       => $item['stt'],
            'thumbnail' => $item['thumbnail'],
            'idItem'    => $item['idItem'],
            'fotos'     => (array_key_exists('anios', $item)) ? $item['fotos'] : [],
            'createdAt' => $fecha->format('d/m H:i'),
        ];
    }

}
