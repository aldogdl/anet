<?php

namespace App\Service\Crawlers;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ToRadec {

    private $client;
    private $folder;

    public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
    {
        $this->client = $client;
        $this->folder = $container->get('recRadec');
    }

    /** */
    public function load(String $uriCall): String
    {
        if($uriCall != '') {

            $index = [];
            $name = base64_encode($uriCall);
            $indexFile = null;
            if(is_file('index_craw.json')) {
                $indexFile = file_get_contents('index_craw.json');
            }
            if($indexFile) {
                $index = json_decode($indexFile);
            }

            if(in_array($name, $index)) {
                
                return file_get_contents($name.'.html');

            }else{

                $index[] = $name;    
                file_put_contents('index_craw.json', json_encode($index));
                $response = $this->client->request(
                    'GET', 'https://www.radec.com.mx/catalogo', [
                        'query' => ['search' => $uriCall, 'op' => 'Buscar']
                    ]
                );

                $statusCode = $response->getStatusCode();
                if($statusCode == 200) {

                    $body = $this->extraerBody($response->getContent());
                    file_put_contents($name.'.html', $body);
                    return $body;
                }
            }
        }

        return '';
    }

    /** */
    private function extraerBody(String $html)
    {
        $htmlRes = '';
        $crawler = new Crawler($html);
        $body = $crawler->filter('.catalog-list-product');

        if($body) {
            foreach ($body as $domElement) {
                $htmlRes .= $domElement->ownerDocument->saveHTML($domElement);
            }
        }
        $elementAnet = '<tr class="catalog-list-product-anet"><td></td></tr>';
        return $elementAnet . $htmlRes;
    }
}